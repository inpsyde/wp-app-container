<?php # -*- coding: utf-8 -*-

namespace PHPSTORM_META {

    override(\Psr\Container\ContainerInterface::get(0),
        map([
            '' => '@',
        ])
    );

    override(\Inpsyde\App\CompositeContainer::get(0),
        map([
            '' => '@',
        ])
    );

    override(\Inpsyde\App\App::resolve(0),
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
