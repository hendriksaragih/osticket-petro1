<?php

require_once INCLUDE_DIR . 'class.plugin.php';

class Petro1PluginConfig extends PluginConfig {

    // Provide compatibility function for versions of osTicket prior to
    // translation support (v1.9.4)
    function translate() {
        if (!method_exists('Plugin', 'translate')) {
            return array(
                function ($x) {
                    return $x;
                },
                function ($x, $y, $n) {
                    return $n != 1 ? $y : $x;
                }
            );
        }
        return Plugin::translate('petro1');
    }

    function getOptions() {
        list ($__, $_N) = self::translate();

        return array(
            'petro1'                      => new SectionBreakField(array(
                'label' => $__('Petro 1 Integrations'),
                'hint'  => $__('Integration with Petro 1')
                    )),
            'petro1-base-url'          => new TextboxField(array(
                'label'         => $__('Base URL'),
                'configuration' => array(
                    'size'   => 100,
                    'length' => 200
                ),
                    )),
            'petro1-api-key' => new TextboxField([
                'label'         => $__('Api Key'),
                'configuration' => [
                    'size'   => 100,
                    'length' => 200
                ],
                    ])
        );
    }

}
