<?php


namespace Junichimura\LaravelDotenvDiffChecker;


use Illuminate\Support\ServiceProvider;

class CommandServiceProvider extends ServiceProvider
{

    const COMMAND = 'command.dotenv.check';

    protected $defer = true;

    public function register()
    {
        $this->app->singleton(self::COMMAND, function () {
            return new DotenvDiffCheckerCommand();
        });

        $this->commands(self::COMMAND);
    }

    public function provides()
    {
        return [
            self::COMMAND
        ];
    }

}