# PHP-WASM Builder V.0.1

PHP to WebAssembly compilation system using Apptainer/Singularity. Generated files are placed in the project root directory:

- `php-web.wasm` - PHP WebAssembly binary
- `php-web.mjs`  - JavaScript module to load the WASM

---

## Project Structure

```
[PHP-WASM Builder]
├── script.php                    # Main build script
└── src                           # Source files
    ├── demo                      # Demo project
    │   └── index.html            # Demo file
    ├── phpw.c                    # PHP wrapper for wasm
    ├── presets                   # Build presets (ZIP files with .def and .ini)
    │   ├── php-8.5.0-default.zip # PHP 8.5.0 and extensions -> .def + default .ini file
    │   └── [...]                 # Other versions/extensions/config files if any...
    └── www                       # Web project files to embed (if any)
        └── [...]                 # Optional content...
```

---

## Prerequisites

- PHP 8.3 or higher
- lz4 (optional, for compression)
- Apptainer/Singularity (https://apptainer.org/)
- Internet connection to download PHP/Libs source code
- Linux operating system (not tested yet in Mac/Windows)

---

## Usage

### Basic Usage

```bash
php script.php
```
1. Select a preset from the list
2. The script will build PHP-WASM using the selected preset

### Options

```bash
php script.php [options]
```

Available options:
- `--www=<path>`  - Custom path for web files (default: `src/www/`)
- `--compress`    - Compress the output .wasm file using lz4
- `--keep-tmp`    - Keep temporary files after build
- `--help`        - Show help message

### Presets

Presets are ZIP files in `src/presets/` containing:
- `php-web.def` - Apptainer definition file
- `php-web.ini` - PHP configuration

### Demo

1. Copy the generated files to the demo folder
   ```bash
   cp php-web.* src/demo/
   ```

2. Start a local server:
   ```bash
   php -S 0.0.0.0:8080
   ```

3. Open http://localhost:8080/src/demo/ in your browser

### Troubleshooting

- **Apptainer not found**: Ensure Apptainer/Singularity is installed and in your PATH
- **Build fails**: Check error messages and ensure all dependencies are installed
- **Compression not working**: Install lz4 or just dont use `--compress` flag

---

## Acknowledgments

Based on the work done by:
- [Sean Morris](https://github.com/seanmorris/php-wasm)
- [Suyoku](https://github.com/soyuka/php-wasm)

