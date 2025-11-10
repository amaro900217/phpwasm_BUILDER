# PHP-WASM Apptainer Container Documentation

## Overview

This container compiles **PHP to WebAssembly (PHP-WASM)** with **libxml2** and **SQLite** support.  
The SIF only sets up the build environment; downloading sources and compiling happens at runtime.  

## Runtime Environment Variables (optional, defaults shown)

- `LIBXML2_TAG=v2.9.10`  
- `PHP_BRANCH=PHP-8.4.14`  
- `WASM_ENVIRONMENT=web`  
- `JAVASCRIPT_EXTENSION=mjs`  
- `EXPORT_NAME=createPhpModule`  
- `MODULARIZE=1`  
- `EXPORT_ES6=1`  
- `ASSERTIONS=0`  
- `OPTIMIZE=-O3`  
- `INITIAL_MEMORY=128mb`  
- `TMPDIR=/tmp` (can mount host folder for large builds)

## Folder Mounts

- `/tmp` → temporary workspace for compilation  
- `/src` → your source code (`phpw.c`)  
- `/output` → generated `.wasm` and `.mjs` files  

Example with default variables:

```bash
apptainer build php-wasm.sif php-wasm.def
apptainer run \
    -B "$(pwd)/temp":/tmp \
    -B "$(pwd)/source":/src \
    -B "$(pwd)":/output \
    php-wasm.sif
```

Custom PHP or libxml2 versions:

```bash
apptainer build php-wasm.sif php-wasm.def
apptainer run \
    -B "$(pwd)/temp":/tmp \
    -B "$(pwd)/source":/src \
    -B "$(pwd)":/output \
    --env PHP_BRANCH=PHP-8.4.20 \
    --env LIBXML2_TAG=v2.10.3 \
    php-wasm.sif
```

## Docker Build Alternative

- Build using Docker:  

```bash
docker buildx bake --no-cache
```

- Produces the same `.wasm` and `.mjs` files without using cached layers.

---

## Notes

- Disk space: compilation can use several GB; mount `/tmp` from host if needed.  
- Emscripten warnings (undefined POSIX symbols, etc.) are normal.  
- Only `.wasm` and `.mjs` files are copied to `/output` to avoid directory conflicts.  

## Run Help

```bash
apptainer run-help php-wasm.sif
```

This displays container-specific instructions and default environment variables.

---

**Based on the repositories by Sean Morris and Suyoku.**

