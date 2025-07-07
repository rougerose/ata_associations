<?php

declare(strict_types=1);

use SpipLeague\EasyCodingStandard\Set\SetList;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
	->withSets([SetList::SPIP])
	// Adapt
	->withPaths([__DIR__])
	->withSkip([__DIR__ . '/lang', __DIR__ . '/vendor', __DIR__ . '/node_modules'])
;
