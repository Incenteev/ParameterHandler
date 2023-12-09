<?php

namespace Incenteev\ParameterHandler\Tests;

use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Composer\Script\Event;
use Incenteev\ParameterHandler\ScriptHandler;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

class ScriptHandlerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<Event>
     */
    private $event;
    /**
     * @var ObjectProphecy<IOInterface>
     */
    private $io;
    /**
     * @var ObjectProphecy<RootPackageInterface>
     */
    private $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = $this->prophesize('Composer\Script\Event');
        $this->io = $this->prophesize('Composer\IO\IOInterface');
        $this->package = $this->prophesize(RootPackageInterface::class);
        $composer = $this->prophesize('Composer\Composer');

        $composer->getPackage()->willReturn($this->package);
        $this->event->getComposer()->willReturn($composer);
        $this->event->getIO()->willReturn($this->io);
    }

    /**
     * @dataProvider provideInvalidConfiguration
     */
    public function testInvalidConfiguration(array $extras, $exceptionMessage)
    {
        $this->package->getExtra()->willReturn($extras);

        chdir(__DIR__);

        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage($exceptionMessage);

        ScriptHandler::buildParameters($this->event->reveal());
    }

    public function provideInvalidConfiguration()
    {
        return array(
            'no extra' => array(
                array(),
                'The parameter handler needs to be configured through the extra.incenteev-parameters setting.',
            ),
            'invalid type' => array(
                array('incenteev-parameters' => 'not an array'),
                'The extra.incenteev-parameters setting must be an array or a configuration object.',
            ),
            'invalid type for multiple file' => array(
                array('incenteev-parameters' => array('not an array')),
                'The extra.incenteev-parameters setting must be an array of configuration objects.',
            ),
            'no file' => array(
                array('incenteev-parameters' => array()),
                'The extra.incenteev-parameters.file setting is required to use this script handler.',
            ),
        );
    }
}
