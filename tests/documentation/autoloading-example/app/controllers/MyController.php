<?php

namespace {
    // All autoloaded classes are recommended to be Pascal Case (each word capitalized, no spaces)
    // It is a requirement that you cannot have an underscore in your class name
    class MyController
    {
        public function index()
        {
            echo 'Doing something';
        }
    }
}

// namespaces are required
// namespaces are the same as the directory structure
// namespaces must follow the same case as the directory structure
// namespaces and directories cannot have any underscores
namespace app\controllers {
    // All autoloaded classes are recommended to be Pascal Case (each word capitalized, no spaces)
    // It is a requirement that you cannot have an underscore in your class name
    class MyController
    {
        public function index() {
            echo __CLASS__ . ' is doing something';
        }
    }
}
