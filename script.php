#!/usr/bin/env php
<?php

/**
 * PHP-WASM Builder Script
 * 
 * This script compiles PHP to WebAssembly using Apptainer/Singularity.
 * It uses .ZIP presets from 'src/presets/' and web files from 'src/www/'.
 * 
 */

// Function to execute commands and display output in real time
function executeCommand($command) {
    passthru($command, $returnVar);
    if ($returnVar !== 0) {
        throw new RuntimeException("Command failed with exit code: $returnVar");
    }
}

// Function to clear screen
function clearScreen() {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        system('cls');
    } else {
        system('clear');
    }
}

// Function to check if Apptainer is installed
function checkApptainer() {
    echo "\n[CHECK] Checking Apptainer...\n";    
    $cmd = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'apptainer --version' : 'apptainer --version 2>&1';
    exec($cmd, $output, $returnVar);
    if ($returnVar !== 0) {
        echo "❌ ERROR: Apptainer not found in PATH.\n";
        echo "   Please install Apptainer/Singularity.\n";
        exit(1);
    }
    echo "  ✅ Apptainer found\n";
    echo "\n[CHECK] Checking internet connection...\n";
    $connected = @fsockopen("8.8.8.8", 53, $errno, $errstr, 2);
    if ($connected) {
        fclose($connected);
        echo "  ✅ Internet connection active\n";
    } else {
        echo "  ⚠️  WARNING: No internet connection detected.\n";
        echo "     Apptainer build might fail if it needs to pull images.\n";
        echo "     Continue anyway? [y/N] ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        if (strtolower($line) !== 'y') {
            exit(1);
        }
    }
}

// Check if lz4 is installed
function checkLz4() {
    echo "\n[CHECK] Checking lz4...\n";
    $cmd = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') 
        ? 'where lz4 2>NUL' 
        : 'which lz4 2>/dev/null';
    exec($cmd, $output, $returnVar);
    if ($returnVar !== 0) {
        echo "  ⚠️  WARNING: 'lz4' not found in the system.\n";
        echo "     The --compress option will not be available.\n";        
        return false;
    }
    echo "  ✅ 'lz4' found\n";
    return true;
}

// Function to clean up temporary files
function cleanup($onError = false) {
    if (is_dir('tmp')) {
        echo "\n[CLEANUP] Removing tmp/...\n";
        deleteDirectory('tmp');
    }
}

// Function to recursively delete a directory (Cross-platform)
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = "$dir/$file";
        (is_dir($path)) ? deleteDirectory($path) : unlink($path);
    }
    rmdir($dir);
}

// Function to clean existing files before starting
function cleanExistingFiles() {
    echo "\n[INITIAL CLEANUP] Removing existing WASM/MJS files...\n";
    $files = glob('*.wasm');
    $files = array_merge($files, glob('*.mjs'));
    $files = array_merge($files, glob('*.wasm.lz4'));
    if (empty($files)) {
        echo "  - No existing .wasm/.mjs/.wasm.lz4 files found\n";
        return;
    }
    foreach ($files as $file) {
        if (file_exists($file)) {
            unlink($file);
            echo "  - Deleted: $file\n";
        }
    }
}

// Function to parse command line arguments
function parseArguments($argv) {
    $options = [
        'keep_tmp' => false,
        'www_path' => 'src/www',
        'compress' => false
    ];
    for ($i = 1; $i < count($argv); $i++) {
        if ($argv[$i] === '--keep-tmp') {
            $options['keep_tmp'] = true;
        } elseif ($argv[$i] === '--www') {
            if (isset($argv[$i + 1])) {
                $options['www_path'] = $argv[$i + 1];
                $i++;
            } else {
                throw new RuntimeException("Missing argument for --www");
            }
        } elseif ($argv[$i] === '--compress') {
            $options['compress'] = true;
        }
    }
    return $options;
}

// Function to list available presets
function listPresets() {
    $presets = glob('src/presets/*.zip');
    if (empty($presets)) {
        throw new RuntimeException("No presets found in src/presets/");
    }
    return $presets;
}

// Function to select a preset
function selectPreset($presets) {
    echo "Available presets:\n\n";
    foreach ($presets as $index => $preset) {
        $name = basename($preset, '.zip');
        echo "  [" . ($index + 1) . "] $name\n";
    }
    echo "\n";
    echo "Select a preset number: ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    $selection = (int)trim($line);
    fclose($handle);
    if ($selection > 0 && $selection <= count($presets)) {
        return $presets[$selection - 1];
    }
    throw new RuntimeException("Invalid selection");
}

// Function to extract preset
function extractPreset($zipPath, $destDir) {
    echo "\n[PRESET] Extracting " . basename($zipPath) . "...\n";
    $zip = new ZipArchive;
    if ($zip->open($zipPath) === TRUE) {
        $zip->extractTo($destDir);
        $zip->close();
        echo "  ✅ Extracted to $destDir\n";
        if (!file_exists("$destDir/php-web.def")) {
            throw new RuntimeException("Preset missing php-web.def");
        }
        if (!file_exists("$destDir/php-web.ini")) {
            throw new RuntimeException("Preset missing php-web.ini");
        }
    } else {
        throw new RuntimeException("Failed to open zip file");
    }
}

// Function to prepare build directory
function prepareBuildDirectory($presetPath, $wwwSource) {
    echo "\n[PREPARE] Setting up build environment...\n";    
    @mkdir('tmp', 0755, true);
    @mkdir('tmp/src', 0755, true);
    extractPreset($presetPath, 'tmp/src');    
    $phpwcSource = 'src/phpw.c';
    if (file_exists($phpwcSource)) {
        $phpwcDest = 'tmp/src/phpw.c';
        if (copy($phpwcSource, $phpwcDest)) {
            echo "  ✅ Copied $phpwcSource to $phpwcDest\n";
        }
    } else {
        echo "⚠️  Warning: $phpwcSource not found\n";
    }    
    if (is_dir($wwwSource)) {
        $wwwDest = 'tmp/src/www';
        echo "  ✅ Copying www folder: $wwwSource to $wwwDest\n";
        $command = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') 
            ? "xcopy /E /I /Y " . escapeshellarg(realpath($wwwSource)) . " " . escapeshellarg(realpath($wwwDest) ?? $wwwDest)
            : "cp -r " . escapeshellarg($wwwSource) . "/. " . escapeshellarg($wwwDest);
        exec($command);
        if (is_dir($wwwDest)) {
            $fileCount = count(glob($wwwDest . '/*'));
            echo "  ✅ Copied www folder ($fileCount items)\n";
        } else {
            throw new RuntimeException("Failed to copy www folder");
        }
    } else {
        echo "⚠️  Warning: www folder not found at $wwwSource\n";
    }
}

// Function to display usage
function displayUsage() {
    echo "\nPHP-WASM Builder Script\n";
    echo "=======================\n\n";
    echo "Automates building custom PHP WASM binaries using Apptainer.\n\n";
    echo "Usage: php script.php [options]\n\n";
    echo "Options:\n";
    echo "  (no args)       Run interactively (select preset from list and continue)\n";
    echo "  --keep-tmp      Do not delete tmp/ directory after build\n";
    echo "  --www <path>    Custom path for web files (default: src/www)\n";
    echo "  --compress      Compress the generated .wasm file using lz4\n";
    echo "  --help          Show this help message\n\n";
    echo "Structure:\n";
    echo "  src/presets/ -> Build presets ZIPs (must contain php-web.def & php-web.ini)\n";
    echo "  src/www/     -> Optional web project files to embed\n\n";
    echo "Workflow:\n";
    echo "  1. Select a preset from src/presets/ (PHP version, optimizations..)\n";
    echo "  2. Script extracts preset & prepares src/www/\n";
    echo "  3. Apptainer compiles the WASM binary\n";
    echo "  4. Output: php-web.wasm & php-web.mjs\n\n";
    echo "Testing:\n";
    echo "  1. cp php-web.wasm php-web.mjs src/demo/\n";
    echo "  2. php -S 0.0.0.0:8080\n";
    echo "  3. Open http://localhost:8080/src/demo/\n\n";
    echo "Note: Existing .wasm/.wasm.lz4/.mjs files are deleted on start. Requires Apptainer & Internet.\n\n";
}

// Start of script
try {
    if (in_array('--help', $argv) || in_array('-h', $argv)) {
        displayUsage();
        exit(0);
    }
    clearScreen();
    echo "=== PHP-WASM Builder Script ===\n\n";
    $options = parseArguments($argv);
    $presets = listPresets();
    $selectedPreset = selectPreset($presets);
    echo "\n[CONFIG] Build configuration:\n";
    echo "  Preset: " . basename($selectedPreset) . "\n";
    checkApptainer();
    $hasLz4 = checkLz4();
    cleanExistingFiles();
    prepareBuildDirectory($selectedPreset, $options['www_path']);
    echo "\n[STEP 1/2] Building Apptainer image...\n";
    echo str_repeat("-", 80) . "\n";
    $defPath = getcwd() . '/tmp/src/php-web.def';
    executeCommand("apptainer build tmp/php-wasm.sif " . escapeshellarg($defPath));
    echo "\n[STEP 2/2] Running container to compile PHP-WASM...\n";
    echo str_repeat("-", 80) . "\n";
    $command = 'apptainer run ';
    $command .= '-B "' . getcwd() . '/tmp":/tmp ';
    $command .= '-B "' . getcwd() . '/tmp/src":/src ';
    $command .= '-B "' . getcwd() . '":/output ';
    $command .= 'tmp/php-wasm.sif';
    executeCommand($command);
    if ($options['keep_tmp']) {
        echo "\n[CLEANUP] Skipped (--keep-tmp active)\n";
    } else {
        cleanup(false);
    }
    echo "\n✨ Process completed successfully! ✨\n\n";
    echo "Generated files:\n";
    $wasmFile = 'php-web.wasm';
    $mjsFile = 'php-web.mjs';
    if (file_exists($wasmFile) && file_exists($mjsFile)) {
        echo "  ✅ $wasmFile\n";
        echo "  ✅ $mjsFile\n";
        if ($options['compress']) {
            if (!$hasLz4) {
                echo "\n❌ Cannot compress: lz4 is not installed.\n";
                echo "   Please install lz4 or run without the --compress option.\n\n";
                exit(1);
            }
            echo "\n[COMPRESS] Compressing binary...\n";
            $lz4File = $wasmFile . '.lz4';
            exec('lz4 --version', $out, $ret);
            if ($ret === 0) {
                executeCommand("lz4 -9 -f $wasmFile $lz4File");
                echo "  ✅ $lz4File (Compressed)\n";
            } else {
                echo "  ⚠️  lz4 not found. Skipping compression.\n";
            }
        }
        echo "\n🚀 To test your build:\n";
        echo "   1. Copy files to demo folder:\n";
        echo "      cp $wasmFile $mjsFile src/demo/\n";
        echo "   2. Run test server:\n";
        echo "      php -S 0.0.0.0:8080\n";
        echo "   3. Open http://localhost:8080/src/demo/\n\n";
    } else {
        echo "❌ No .mjs/.wasm generated files found\n";
        echo "   Check the output above for any errors\n";
    }
    
} catch (Exception $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n\n";
    if (isset($options['keep_tmp']) && $options['keep_tmp']) {
        echo "\n[CLEANUP] Skipped (--keep-tmp active)\n";
    } else {
        cleanup(true);
    }
    exit(1);
}
