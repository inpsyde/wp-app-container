<?php

return [
    'target_php_version' => null,
    'backward_compatibility_checks' => false,
    'guess_unknown_parameter_type_using_default' => true,
    'allow_method_param_type_widening' => true,
    'strict_method_checking' => true,
    'strict_param_checking' => true,
    'strict_property_checking' => true,
    'strict_return_checking' => true,
    'dead_code_detection' => true,
    'unused_variable_detection' => true,
    'warn_about_redundant_use_namespaced_class' => true,
    'skip_slow_php_options_warning' => true,
    'directory_list' => [
        'src/',
        'vendor/psr/container/src',
        'vendor/pimple/pimple/src',
        'vendor/roots/wordpress',
    ],
    'exclude_analysis_directory_list' => [
        'vendor/',
    ],
    'exclude_file_list' => [
        'vendor/roots/wordpress/wp-includes/compat.php',
    ],
    'suppress_issue_types' => [
        'PhanUnreferencedPublicMethod',
    ],
    'plugins' => [
        'AlwaysReturnPlugin',
        'DuplicateArrayKeyPlugin',
        'PregRegexCheckerPlugin',
        'PrintfCheckerPlugin',
        'UnreachableCodePlugin',
    ],
];