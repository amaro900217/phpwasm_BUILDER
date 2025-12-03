# PHP-WASM Builder V.0.1

A PHP to WebAssembly compilation system using Apptainer/Singularity. The generated files are placed in the project root directory:

- `phpw.wasm` - PHP WebAssembly binary
- `phpw.mjs` - JavaScript module to load the WebAssembly (WASM)

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
    │   └── [...]                 # Other versions/extensions/config presets...
    └── www                       # Web project files to embed (if any)
        └── [...]                 # Optional content...
```

---

## System Prerequisites

- PHP >= 8.3.6 with php-zip
- lz4 (*optional*, for --compress)
- Apptainer/Singularity (https://apptainer.org/)
- Internet connection (to download source code when using presets)
- Linux OK; Windows via WSL (inside WSL filesystem only!); Mac OS (not tested)

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
- `--compress`    - Compress the output .wasm file using lz4 (removes original .wasm file)
- `--keep-tmp`    - Keep temporary files after build
- `--help`        - Show help message and exit

### Presets

Presets are ZIP files in `src/presets/` containing:
- `phpw.def` - Apptainer definition file
- `phpw.ini` - PHP configuration

### Demo

1. Start a local server:
   ```bash
   php -S 0.0.0.0:8080
   ```

2. Open http://localhost:8080/src/demo/ in your browser

### Troubleshooting

- **Apptainer not found**: Ensure Apptainer/Singularity is installed and in your PATH
- **Build fails**: Check error messages and ensure all dependencies are installed
- **Compression not working**: Install lz4 or run without the `--compress` flag

---

## Acknowledgments

Based on the work done by:
- [Sean Morris](https://github.com/seanmorris/php-wasm)
- [Suyoku](https://github.com/soyuka/php-wasm)

