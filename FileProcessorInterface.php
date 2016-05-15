<?php

namespace Incenteev\ParameterHandler;

interface FileProcessorInterface
{
    public function parse($file);

    public function dump($file, $values);

    public function supports($extension);
}
