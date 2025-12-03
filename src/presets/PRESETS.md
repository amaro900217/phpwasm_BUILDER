# PHP-WASM Presets

This document describes the available presets for compiling PHP to WebAssembly using the PHP-WASM Builder.

## Available Presets

### 1. php-8.5.0-default

**PHP Version:** 8.5.0

**Description:**
A balanced preset with commonly used extensions and features enabled. Suitable for most web applications that require standard PHP functionality.

**Included Extensions:**
- Standard PHP extensions
- JSON
- Session
- Tokenizer
- XML (libxml2)
- SQLite3
- PDO (SQLite driver)
- Fileinfo
- PCRE
- Date
- SPL
- Filter
- Hash
- Reflection
- Standard PHP library (SPL)
- Phar

**Configuration Highlights:**
- Memory limit: 256MB
- Max execution time: 30 seconds
- File uploads: Enabled (8MB max)
- Error reporting: E_ALL except E_DEPRECATED and E_STRICT
- Session handling: File-based sessions in /tmp
- Output buffering: 4096 bytes

**Use Cases:**
- General-purpose web applications
- API backends
- Development environments

### 2. php-8.5.0-minimal

**PHP Version:** 8.5.0

**Description:**
A minimal PHP build with only essential extensions enabled. This results in a smaller WebAssembly binary size at the cost of some functionality.

**Included Extensions:**
- Core PHP functionality
- JSON
- Tokenizer
- PCRE
- Date
- SPL
- Filter
- Hash
- Standard PHP library (SPL)

**Configuration Highlights:**
- Memory limit: 128MB
- Max execution time: 30 seconds
- File uploads: Disabled
- Error reporting: E_ALL except E_DEPRECATED and E_STRICT
- Minimal extension set for reduced binary size

**Use Cases:**
- Microservices with minimal dependencies
- When binary size is a critical factor
- Learning/experimental projects

## How to Use a Preset

1. Run the build script:
   ```bash
   php script.php
   ```
2. Select the desired preset from the list
3. The script will compile PHP to WebAssembly using the selected preset

## Creating Custom Presets

You can create your own presets by creating a new ZIP in the `src/presets/` directory with the following structure:

```
preset-name.zip/
  ├── phpw.def    # Apptainer/Singularity definition file
  └── phpw.ini    # PHP configuration file
```

## Notes

- The WebAssembly build process may take several minutes depending on your system resources
- Some PHP functions may behave differently in a WebAssembly environment
- File system operations are emulated and may have limitations
- Network access is restricted by the browser's same-origin policy
