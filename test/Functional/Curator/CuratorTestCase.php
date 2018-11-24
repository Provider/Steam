<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional\Curator;

use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Porter;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorSession;
use ScriptFUSIONTest\Porter\Provider\Steam\FixtureFactory;

abstract class CuratorTestCase extends TestCase
{
    /**
     * @var Porter
     */
    protected static $porter;

    /**
     * @var CuratorSession
     */
    protected static $session;

    public static function setUpBeforeClass()
    {
        self::$porter = FixtureFactory::createPorter();
        self::$session = \Amp\Promise\wait(FixtureFactory::createSession(self::$porter));
    }
}
