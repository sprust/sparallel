<?php

namespace SParallel\Output;

use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\VarDumper;

class OutputControl
{
    private static bool $isActive = false;

    private static ?bool $varDumperIsExists = null;

    private static ?object $originalDumper = null;

    public static function disable(): void
    {
        if (self::$isActive) {
            return;
        }

        if (self::isVarDumperExists()) {
            self::$originalDumper = VarDumper::setHandler(null);

            VarDumper::setHandler(function ($var) {
                $cloner = new VarCloner();
                $dumper = new CliDumper('php://memory');
                $dumper->dump($cloner->cloneVar($var));
            });
        }

        ob_start(static fn($buffer) => '');

        self::$isActive = true;
    }

    public static function enable(): string
    {
        if (!self::$isActive) {
            return '';
        }

        $output = ob_get_clean();

        if (self::$originalDumper !== null) {
            VarDumper::setHandler(self::$originalDumper);
            self::$originalDumper = null;
        }

        self::$isActive = false;

        return $output;
    }

    private static function isVarDumperExists(): bool
    {
        return self::$varDumperIsExists ??= class_exists(VarDumper::class);
    }
}
