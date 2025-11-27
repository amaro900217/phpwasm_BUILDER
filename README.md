# phpwasm_BUILDER

Automated build system for PHP 8.5 on WebAssembly using Apptainer. This project provides a complete solution to compile PHP to WebAssembly (WASM) with libxml2, SQLite, and mbstring support.

## 🚀 Quick Start

### Prerequisites
- Apptainer installed on your system
- PHP CLI (for the build script)

### Automated Build (Recommended)

The easiest way to build PHP-WASM is using the automated script:

```bash
php build.php
```

The script will:
- ✅ Check if Apptainer is installed
- 🧹 Clean existing WASM/MJS files
- 🏗️ Build the Apptainer image
- 🚀 Run the container to compile PHP-WASM
- 🧹 Clean up temporary files
- 🌐 Suggest how to test the build

### Manual Build With a .sif Image

If you prefer to build manually:

```bash
apptainer build php-wasm.sif php-wasm.def
apptainer run \
    -B "$(pwd)/temp":/tmp \
    -B "$(pwd)/source":/src \
    -B "$(pwd)":/output \
    php-wasm.sif
```

## 📋 Configuration

### Environment Variables

You can customize the build with these environment variables:

| Variable | Default | Description |
|----------|---------|-------------|
| `LIBXML2_TAG` | `v2.9.10` | libxml2 version to use |
| `PHP_BRANCH` | `php-8.5.0` | PHP version to compile |
| `WASM_ENVIRONMENT` | `web` | Target environment |
| `JAVASCRIPT_EXTENSION` | `mjs` | Output file extension |
| `EXPORT_NAME` | `createPhpModule` | Module export name |
| `MODULARIZE` | `1` | Enable modularization |
| `EXPORT_ES6` | `1` | Export as ES6 module |
| `ASSERTIONS` | `0` | Enable/disable assertions |
| `OPTIMIZE` | `-Oz` | Optimization level |
| `INITIAL_MEMORY` | `64mb` | Initial WASM memory |
| `TMPDIR` | `/temp` | Temporary directory |

### Custom Build Example

```bash
php build.php
# Or with custom variables:
apptainer run \
    -B "$(pwd)/temp":/tmp \
    -B "$(pwd)/source":/src \
    -B "$(pwd)":/output \
    --env PHP_BRANCH=php-8.5.0 \
    --env INITIAL_MEMORY=128mb \
    php-wasm.sif
```

## 📁 Project Structure

```
phpwasm_BUILDER/
├── build.php          # Automated build script
├── php-wasm.def       # Apptainer definition file
├── index.html         # Example test page
├── source/            # Source code directory
├── temp/              # Temporary build files
└── README.md          # This file
```

## 🧪 Testing Your Build

After the build completes, test it with:

```bash
php -S 0.0.0.0:8080
```

Then open `http://localhost:8080` in your browser. If `index.html` exists, it will run `phpinfo()` automatically.

## 🔧 Build Features

### Automated Script Features
- **Real-time output**: See build progress as it happens
- **Error handling**: Automatic cleanup on failure
- **Smart cleanup**: Removes temporary files and build artifacts
- **Dependency checking**: Verifies Apptainer installation
- **Build verification**: Checks if WASM/MJS files were generated

### Included PHP Extensions
- **libxml2**: XML processing support
- **SQLite**: Database support
- **mbstring**: Multi-byte string handling
- **json**: JSON support
- **ctype**: Character type checking
- **tokenizer**: PHP token parsing
- **fileinfo**: File type detection
- **hash**: Hashing functions
- **session**: Session management
- **filter**: Data filtering
- **dom**: DOM manipulation
- **pdo**: Database abstraction
- **pdo-sqlite**: SQLite PDO driver

## ⚠️ Important Notes

- **Disk space**: Compilation can use several GB of space
- **Build time**: The process can take 10-30+ minutes depending on your system
- **Emscripten warnings**: Undefined POSIX symbol warnings are normal and can be ignored
- **Memory**: Ensure sufficient RAM (4GB+ recommended) for compilation

## 🐛 Troubleshooting

### Common Issues

1. **Apptainer not found**
   ```bash
   # Install Apptainer
   # Follow: https://apptainer.org/docs/admin/main/installation.html
   ```

2. **Permission denied**
   ```bash
   chmod +x build.php
   ```

3. **Build fails due to disk space**
   ```bash
   # Clean up temp directory
   rm -rf temp/*
   ```

4. **WASM files not generated**
   - Check the build output for errors
   - Ensure all dependencies are installed
   - Try with more memory: `--env INITIAL_MEMORY=128mb`

## 📚 Technical Details

The build process:
1. Sets up Emscripten environment
2. Downloads and compiles libxml2
3. Downloads and compiles SQLite
4. Downloads and compiles Oniguruma (for mbregex)
5. Downloads and compiles PHP 8.5
6. Links everything into a single WASM module
7. Generates JavaScript module wrapper

## 🙏 Acknowledgments

Based on the excellent work by:
- [Sean Morris](https://github.com/seanmorris/php-wasm)
- [Suyoku](https://github.com/soyuka/php-wasm)

## 📄 License

This project maintains the same license as the original PHP-WASM projects.

