#!/usr/bin/env php
<?php

/**
 * PHP-WASM Builder Script
 * 
 * This script compiles PHP to WebAssembly using Apptainer/Singularity.
 * It uses .ZIP presets from 'src/presets/' and web files from 'src/www/'.
 * 
 */

/**
 * Execute a shell command and display its output in real-time
 * 
 * This function runs a shell command using passthru() to display output
 * as it's generated. It throws an exception if the command fails.
 * 
 * @param string $command The shell command to execute
 * @throws RuntimeException If the command returns a non-zero exit code
 * @return void
 */
function executeCommand($command) {
    passthru($command, $returnVar);
    if ($returnVar !== 0) {
        throw new RuntimeException("Command failed with exit code: $returnVar");
    }
}

/**
 * Clear the terminal/console screen in a cross-platform manner
 * 
 * This function detects the operating system and uses the appropriate command
 * to clear the terminal screen. It supports both Windows (cls) and Unix-like
 * systems (clear).
 * 
 * @return void
 */
function clearScreen() {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        system('cls');
    } else {
        system('clear');
    }
}

/**
 * Verify Apptainer/Singularity installation and internet connectivity
 * 
 * This function performs two critical checks:
 * 1. Verifies that Apptainer/Singularity is installed and accessible in the system PATH
 * 2. Tests internet connectivity which is required for downloading dependencies
 * 
 * @throws RuntimeException If Apptainer is not found
 * @return void
 */
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

/**
 * Check if lz4 compression utility is available in the system
 * 
 * This function verifies if the lz4 compression tool is installed and accessible
 * from the system's PATH. It's used to determine if the --compress option can be used.
 * 
 * @return bool Returns true if lz4 is installed and available, false otherwise
 */
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

/**
 * Clean up temporary files and directories
 * 
 * @param bool $onError If true, indicates the cleanup is happening after an error
 * @return void
 */
function cleanup($onError = false) {
    if (is_dir('tmp')) {
        echo "\n[CLEANUP] Removing tmp/...\n";
        deleteDirectory('tmp');
    }
}

/**
 * Recursively delete a directory and its contents (Cross-platform)
 * 
 * @param string $dir The directory path to delete
 * @return void
 */
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

/**
 * Remove existing WebAssembly and JavaScript module files before starting a new build
 * 
 * This function removes any .wasm, .mjs, and .wasm.lz4 files in the current directory
 * to prevent conflicts with new builds.
 * 
 * @return void
 */
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

/**
 * Parse command line arguments and return an array of options
 * 
 * Supported arguments:
 * --keep-tmp  Keep temporary files after build
 * --www=path  Custom path for web files (default: 'src/www/')
 * --compress  Enable compression of output files
 * 
 * @param array $argv Command line arguments
 * @return array Parsed options
 */
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

/**
 * Scan and list all available preset files in the presets directory
 * 
 * This function searches for .zip files in the src/presets/ directory and
 * returns an array of absolute paths to each preset file. Presets are
 * expected to be ZIP archives containing PHP-WASM build configurations.
 * 
 * @throws RuntimeException If no preset files are found
 * @return array Array of absolute paths to preset files
 */
function listPresets() {
    $presets = glob('src/presets/*.zip');
    if (empty($presets)) {
        throw new RuntimeException("No presets found in src/presets/");
    }
    return $presets;
}

/**
 * Display an interactive menu to select a preset from the available options
 * 
 * This function presents a numbered list of available presets and prompts the user
 * to select one. It validates the input and returns the path to the selected preset.
 * 
 * @param array $presets Array of preset file paths
 * @throws RuntimeException If an invalid selection is made
 * @return string Path to the selected preset file
 */
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

/**
 * Extract a preset ZIP file to the specified destination directory
 * 
 * This function extracts the contents of a preset ZIP file to the target directory.
 * It ensures the destination exists and validates that the ZIP file contains
 * the required files (phpw.def and phpw.ini).
 * 
 * @param string $zipPath Path to the preset ZIP file
 * @param string $destDir Destination directory for extraction
 * @throws RuntimeException If extraction fails or required files are missing
 * @return void
 */
function extractPreset($zipPath, $destDir) {
    echo "\n[PRESET] Extracting " . basename($zipPath) . "...\n";
    $zip = new ZipArchive;
    if ($zip->open($zipPath) === TRUE) {
        $zip->extractTo($destDir);
        $zip->close();
        echo "  ✅ Extracted to $destDir\n";
        if (!file_exists("$destDir/phpw.def")) {
            throw new RuntimeException("Preset missing phpw.def");
        }
        if (!file_exists("$destDir/phpw.ini")) {
            throw new RuntimeException("Preset missing phpw.ini");
        }
    } else {
        throw new RuntimeException("Failed to open zip file");
    }
}

/**
 * Prepare the build directory structure and copy necessary files
 * 
 * This function sets up the build environment by:
 * 1. Creating necessary temporary directories
 * 2. Extracting the selected preset
 * 3. Copying the PHP-WASM wrapper (phpw.c)
 * 4. Copying the www directory with web files
 * 
 * @param string $presetPath Path to the selected preset ZIP file
 * @param string $wwwSource  Path to the source www directory
 * @throws RuntimeException If any critical file operations fail
 * @return void
 */
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

/**
 * Display usage information and command-line options
 * 
 * This function outputs a formatted help message showing all available
 * command-line options and their descriptions. It provides users with
 * information on how to use the PHP-WASM Builder script effectively.
 * 
 * The displayed information includes:
 * - Available command-line options
 * - Default values for optional parameters
 * - Examples of common usage patterns
 * 
 * @return void
 */
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
    echo "  src/presets/ -> Build presets ZIPs (must contain phpw.def & phpw.ini)\n";
    echo "  src/www/     -> Optional web project files to embed\n\n";
    echo "Workflow:\n";
    echo "  1. Select a preset from src/presets/ (PHP version, optimizations..)\n";
    echo "  2. Script extracts preset & prepares src/www/\n";
    echo "  3. Apptainer compiles the WASM binary\n";
    echo "  4. Output: phpw.wasm & phpw.mjs\n\n";
    echo "Testing:\n";
    echo "  1. php -S 0.0.0.0:8080\n";
    echo "  2. Open http://localhost:8080/src/demo/\n\n";
    echo "Note: Existing .wasm/.wasm.lz4/.mjs files are deleted on start. Requires Apptainer & Internet.\n\n";
}

/**
 * Main script execution
 * 
 * This is the entry point that orchestrates the PHP-WASM build process:
 * 1. Parse command line arguments
 * 2. Set up build environment
 * 3. Build Apptainer image
 * 4. Compile PHP to WebAssembly
 * 5. Handle post-build operations
 * 
 * @global array $argv Command line arguments
 */
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
    $defPath = getcwd() . '/tmp/src/phpw.def';
    executeCommand("apptainer build --force tmp/php-wasm.sif " . escapeshellarg($defPath));
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
    $wasmFile = 'phpw.wasm';
    $mjsFile = 'phpw.mjs';
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
                executeCommand("lz4 --best -B $wasmFile $lz4File");
                // executeCommand("lz4 -9 -f $wasmFile $lz4File");
                // Remove original .wasm file after successful compression
                if (file_exists($lz4File)) {
                    unlink($wasmFile);
                    echo "  ✅ $lz4File (Compressed, original .wasm removed)\n";
                } else {
                    echo "  ⚠️  Compression may have failed, keeping original .wasm file\n";
                }
            } else {
                echo "  ⚠️  lz4 not found. Skipping compression.\n";
            }
        }
        echo "\n🚀 To test your build:\n";
        echo "   1. Run test server:\n";
        echo "      php -S 0.0.0.0:8080\n";
        echo "   2. Open http://localhost:8080/src/demo/\n\n";
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
