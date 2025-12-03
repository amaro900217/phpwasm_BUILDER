#include "sapi/embed/php_embed.h"
#include <emscripten.h>
#include <stdlib.h>

#include "zend_globals_macros.h"
#include "zend_exceptions.h"
#include "zend_closures.h"

int main() {
    return 0;
}

void phpw_flush() {
    fprintf(stdout, "\n");
    fprintf(stderr, "\n");
}

void EMSCRIPTEN_KEEPALIVE phpw_eval(char *code) {
    setenv("USE_ZEND_ALLOC", "0", 1);
    php_embed_init(0, NULL);
    zend_eval_string(code, NULL, "phpw_eval run script");
    if (EG(exception)) {
        zend_exception_error(EG(exception), E_ERROR);
        zend_clear_exception();
    }
    phpw_flush();
    php_embed_shutdown();
}

int EMBED_SHUTDOWN = 1;

void EMSCRIPTEN_KEEPALIVE phpw_run(char *file) {
    setenv("USE_ZEND_ALLOC", "0", 1);
    if (EMBED_SHUTDOWN == 0) {
        php_embed_shutdown();
    }
    php_embed_init(0, NULL);
    EMBED_SHUTDOWN = 0;
    zend_file_handle file_handle;
    zend_stream_init_filename(&file_handle, file);
    int result = php_execute_script(&file_handle);
    if (EG(exception)) {
        zend_exception_error(EG(exception), E_ERROR);
        zend_clear_exception();
    }
    zend_destroy_file_handle(&file_handle);
    phpw_flush();
    php_embed_shutdown();
    EMBED_SHUTDOWN = 1;
}