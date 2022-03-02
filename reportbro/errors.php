<?php

namespace Reportbro;

class ReportBroError extends \Exception {
    function __construct($error) {
        $this->error = $error;
    }
}

class StandardError {
    function __construct($msg_key, $object_id = null, $field = null, $info = null, $context = null) {
        $this->msg_key = $msg_key;
        $this->object_id = $object_id;
        $this->field = $field;
        $this->info = $info;
        $this->context = $context;
    }
}
