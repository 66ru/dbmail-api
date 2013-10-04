<?php

return array(
    'or',
    array(
        array(
            'Message Size' => array(
                'operation' => 'greater than',
                'value' => '10000'
            ),
        ),
        array(
            'From' => array(
                'operation' => 'is',
                'value' => '*from*'
            )
        ),
    ),
    array(
        'Mirror to' => 'to@mail.ru'
    ),
    true,
);