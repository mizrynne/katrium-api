<?php

return [
    'bootstrap_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('BOOTSTRAP_EMAILS', ''))
    ))),
];