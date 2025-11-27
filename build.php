<?php

// Function to execute commands and display output in real time
function executeCommand($command) {
    $descriptors = [
        0 => ["pipe", "r"],  // stdin
        1 => ["pipe", "w"],  // stdout
        2 => ["pipe", "w"]   // stderr
    ];

    $process = proc_open($command, $descriptors, $pipes, getcwd(), null, ['bypass_shell' => true]);

    if (!is_resource($process)) {
        die("Cannot execute command: $command\n");
    }

    // Read output in real time
    $output = '';
    $stderr = '';
    $stdout = '';
    $status = 0;

    // Configure streams for non-blocking
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $running = true;
    while ($running) {
        $read = [$pipes[1], $pipes[2]];
        $write = null;
        $except = null;

        // Wait for data to read
        if (stream_select($read, $write, $except, 0, 200000) !== false) {
            // Read stdout
            $chunk = fread($pipes[1], 8192);
            if ($chunk !== false) {
                $stdout .= $chunk;
                echo $chunk;
                @flush();
            }

            // Read stderr
            $chunk = fread($pipes[2], 8192);
            if ($chunk !== false) {
                $stderr .= $chunk;
                fwrite(STDERR, $chunk);
                @flush();
            }
        }

        // Check if process has finished
        $status = proc_get_status($process);
        if (!$status['running']) {
            $running = false;
        }
    }

    // Close pipes
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    // Close process
    $exitCode = proc_close($process);

    if ($exitCode !== 0) {
        throw new RuntimeException("Command failed with exit code: $exitCode\n$stderr");
    }

    return $stdout;
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
    echo "[CHECK] Checking if Apptainer is installed...\n";
    
    // Try to execute apptainer --version directly
    $versionOutput = shell_exec('apptainer --version 2>&1');
    $exitCode = 0;
    
    // Check if command exists and works
    if (strpos($versionOutput, 'apptainer') === false || strpos($versionOutput, 'not found') !== false || strpos($versionOutput, 'command not found') !== false) {
        echo "❌ ERROR: Apptainer is not installed or not in PATH.\n";
        echo "Please install Apptainer before continuing.\n";
        echo "Instructions: https://apptainer.org/docs/admin/main/installation.html\n";
        exit(1);
    }
    
    // Additional verification with which
    $whichOutput = shell_exec('which apptainer 2>/dev/null');
    if (empty(trim($whichOutput))) {
        echo "❌ ERROR: Apptainer not found in PATH.\n";
        echo "Make sure Apptainer is installed and accessible.\n";
        exit(1);
    }
    
    echo "✅ Apptainer found: " . trim($versionOutput) . "\n";
    echo "✅ Location: " . trim($whichOutput) . "\n";
}

// Function to clean up temporary files
function cleanup($onError = false) {
    echo "[CLEANUP] Removing temporary files...\n";
    
    // Always delete .sif if exists
    if (file_exists('php-wasm.sif')) {
        unlink('php-wasm.sif');
        echo "- File php-wasm.sif deleted\n";
    }
    
    // Always delete temp/build folder if exists
    $buildDir = 'temp/build';
    if (is_dir($buildDir)) {
        // Use recursive directory deletion
        deleteDirectory($buildDir);
        echo "- Folder temp/build deleted\n";
    }
}

// Function to recursively delete a directory
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    // Get all files and directories (including hidden ones)
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            // Recursively delete subdirectory
            deleteDirectory($path);
        } else {
            // Delete file
            unlink($path);
        }
    }
    
    // Delete the directory itself
    rmdir($dir);
}

// Function to clean existing files before starting
function cleanExistingFiles() {
    echo "[INITIAL CLEANUP] Removing existing WASM/MJS files...\n";
    
    $files = glob('*.wasm');
    $files = array_merge($files, glob('*.mjs'));
    
    if (empty($files)) {
        echo "- No existing .wasm/.mjs files found\n";
        return;
    }
    
    foreach ($files as $file) {
        if (file_exists($file)) {
            unlink($file);
            echo "- Deleted: $file\n";
        }
    }
    echo "\n";
}

// Start of script
try {
    clearScreen();
    echo "=== Starting PHP-WASM build process ===\n\n";
    
    // Check if Apptainer is installed
    checkApptainer();

    // Clean existing files before starting
    cleanExistingFiles();

    // Create necessary directories
    @mkdir('temp', 0755, true);
    @mkdir('source', 0755, true);

    // 1. Build Apptainer image
    echo "\n[STEP 1/2] Building Apptainer image...\n";
    echo str_repeat("-", 80) . "\n";
    
    executeCommand('apptainer build php-wasm.sif php-wasm.def');
    
    // 2. Run container
    echo "\n[STEP 2/2] Running container to compile PHP-WASM...\n";
    echo str_repeat("-", 80) . "\n";
    
    $command = 'apptainer run ';
    $command .= '-B "' . getcwd() . '/temp":/tmp ';
    $command .= '-B "' . getcwd() . '/source":/src ';
    $command .= '-B "' . getcwd() . '":/output ';
    $command .= 'php-wasm.sif';
    
    executeCommand($command);
    
    // 3. Clean temporary files (success)
    cleanup(false);
    
    echo "\nProcess completed successfully!\n";
    echo "Generated files are in the current directory.\n";
    
    // Check if generated files exist to suggest testing
    if (file_exists('php-web.mjs') || file_exists('php-web.wasm')) {
        echo "To test the build, you can run:\n";
        echo "php -S 0.0.0.0:8080\n";
        echo "Then open http://localhost:8080 in your browser\n";
        
        // Also check if index.html exists
        if (file_exists('index.html')) {
            echo "✅ index.html found - ready to test!\n";
        } else {
            echo "❌ index.html not found - make sure you have a test page\n";
        }
    } else {
        echo "❌ No .mjs/.wasm generated files found\n";
        echo "   Check the output above for any errors\n";
    }
    
} catch (Exception $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    
    // Clean temporary files (error)
    cleanup(true);
    
    exit(1);
}
