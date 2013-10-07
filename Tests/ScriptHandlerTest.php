<?php

namespace Incenteev\ParameterHandler\Tests;

use Incenteev\ParameterHandler\ScriptHandler;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class ScriptHandlerTest extends ProphecyTestCase
{
    private $event;
    private $io;
    private $package;
    private $environmentBackup = array();

    protected function setUp()
    {
        parent::setUp();

        $this->event = $this->prophesize('Composer\Script\Event');
        $this->io = $this->prophesize('Composer\IO\IOInterface');
        $this->package = $this->prophesize('Composer\Package\PackageInterface');
        $composer = $this->prophesize('Composer\Composer');

        $composer->getPackage()->willReturn($this->package);
        $this->event->getComposer()->willReturn($composer);
        $this->event->getIO()->willReturn($this->io);
    }

    protected function tearDown()
    {
        parent::tearDown();

        foreach ($this->environmentBackup as $var => $value) {
            if (false === $value) {
                putenv($var);
            } else {
                putenv($var.'='.$value);
            }
        }
    }

    /**
     * @dataProvider provideInvalidConfiguration
     */
    public function testInvalidConfiguration(array $extras, $exceptionMessage)
    {
        $this->package->getExtra()->willReturn($extras);

        chdir(__DIR__);

        $this->setExpectedException('InvalidArgumentException', $exceptionMessage);

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
            'missing default dist file' => array(
                array('incenteev-parameters' => array(
                    'file' => 'fixtures/invalid/missing.yml',
                )),
                'The dist file "fixtures/invalid/missing.yml.dist" does not exist. Check your dist-file config or create it.',
            ),
            'missing custom dist file' => array(
                array('incenteev-parameters' => array(
                    'file' => 'fixtures/invalid/missing.yml',
                    'dist-file' => 'fixtures/invalid/non-existent.dist.yml',
                )),
                'The dist file "fixtures/invalid/non-existent.dist.yml" does not exist. Check your dist-file config or create it.',
            ),
            'missing top level key in dist file' => array(
                array('incenteev-parameters' => array(
                    'file' => 'fixtures/invalid/missing_top_level.yml',
                )),
                'The dist file seems invalid.',
            ),
            'invalid values in the existing file' => array(
                array('incenteev-parameters' => array(
                    'file' => 'fixtures/invalid/invalid_existing_values.yml',
                )),
                'The existing "fixtures/invalid/invalid_existing_values.yml" file does not contain an array',
            ),
        );
    }

    /**
     * @dataProvider provideParameterHandlingTestCases
     */
    public function testParameterHandling($testCaseName)
    {
        $dataDir = __DIR__.'/fixtures/testcases/'.$testCaseName;

        $testCase = array_replace_recursive(
            array(
                'title' => 'unknown test',
                'config' => array(
                    'file' => 'parameters.yml',
                ),
                'dist-file' => 'parameters.yml.dist',
                'environment' => array(),
                'interactive' => false,
            ),
            (array) Yaml::parse($dataDir.'/setup.yml')
        );

        $workingDir = sys_get_temp_dir() . '/incenteev_parameter_handler';
        $exists = $this->initializeTestCase($testCase, $dataDir, $workingDir);

        $this->package->getExtra()->willReturn(array('incenteev-parameters' => $testCase['config']));

        $message = sprintf('<info>%s the "%s" file</info>', $exists ? 'Updating' : 'Creating', $testCase['config']['file']);
        $this->io->write($message)->shouldBeCalled();

        $this->setInteractionExpectations($testCase);

        ScriptHandler::buildParameters($this->event->reveal());

        $this->assertFileEquals($dataDir.'/expected.yml', $workingDir.'/'.$testCase['config']['file'], $testCase['title']);
    }

    private function initializeTestCase(array $testCase, $dataDir, $workingDir)
    {
        $fs = new Filesystem();

        if (is_dir($workingDir)) {
            $fs->remove($workingDir);
        }

        $fs->copy($dataDir.'/dist.yml', $workingDir.'/'. $testCase['dist-file']);

        if ($exists = file_exists($dataDir.'/existing.yml')) {
            $fs->copy($dataDir.'/existing.yml', $workingDir.'/'.$testCase['config']['file']);
        }

        foreach ($testCase['environment'] as $var => $value) {
            $this->environmentBackup[$var] = getenv($var);
            putenv($var.'='.$value);
        };

        chdir($workingDir);

        return $exists;
    }

    private function setInteractionExpectations(array $testCase)
    {
        $this->io->isInteractive()->willReturn($testCase['interactive']);

        if (!$testCase['interactive']) {
            return;
        }

        if (!empty($testCase['requested_params'])) {
            $this->io->write('<comment>Some parameters are missing. Please provide them.</comment>')->shouldBeCalledTimes(1);
        }

        foreach ($testCase['requested_params'] as $param => $settings) {
            $this->io->ask(sprintf('<question>%s</question> (<comment>%s</comment>): ', $param, $settings['default']), $settings['default'])
                ->willReturn($settings['input'])
                ->shouldBeCalled();
        }
    }

    public function provideParameterHandlingTestCases()
    {
        $tests = array();

        foreach (glob(__DIR__.'/fixtures/testcases/*/') as $folder) {
            $tests[] = array(basename($folder));
        }

        return $tests;
    }
}
