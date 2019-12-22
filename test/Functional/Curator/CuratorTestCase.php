<?php
declare(strict_types=1);

namespace ScriptFUSIONTest\Porter\Provider\Steam\Functional\Curator;

use PHPUnit\Framework\TestCase;
use ScriptFUSION\Porter\Porter;
use ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorSession;
use ScriptFUSIONTest\Porter\Provider\Steam\FixtureFactory;
use function Amp\Promise\wait;

abstract class CuratorTestCase extends TestCase
{
    protected const CURATOR_ID = 31457321;

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
        self::$session = wait(FixtureFactory::createSession(self::$porter));
    }
}
