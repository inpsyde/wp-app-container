<?php # -*- coding: utf-8 -*-

namespace PHPSTORM_META {

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
