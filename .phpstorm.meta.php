<?php # -*- coding: utf-8 -*-

namespace PHPSTORM_META {

    override(\Inpsyde\App\Container::get(0),
        map([
            '' => '@',
        ])
    );

    override(\Inpsyde\App\App::resolve(0),
        map([
            '' => '@',
        ])
    );

    override(\Inpsyde\App\App::make(0),
        map([
            '' => '@',
        ])
    );

    override(\Mockery::mock(0),
        map([
            '' => '@',
        ])
    );
}
