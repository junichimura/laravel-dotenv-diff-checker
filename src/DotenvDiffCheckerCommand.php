<?php


namespace Junichimura\LaravelDotenvDiffChecker;

use Illuminate\Console\Command;

class DotenvDiffCheckerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dotenv:check {--m|main=.env} {--e|example=.env.example} {--separator_crlf} {--separator_cr}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compare file [.env] and [.env.example]';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $mainFile = $this->option('main');
        $exampleFile = $this->option('example');

        if ($this->option('separator_crlf')) {
            $lineSeparator = "\r\n";
        } elseif ($this->option('separator_cr')) {
            $lineSeparator = "\r";
        } else {
            $lineSeparator = "\n";
        }

        $this->info(sprintf('=== Compare file [%s] and [%s] ===', $mainFile, $exampleFile));

        if ($mainContents = @file_get_contents($mainFile)) {
            $mainEnvKeys = collect(explode($lineSeparator, $mainContents))->map(function ($line) {
                return explode('=', $line)[0];
            })->filter($this->filterEmptyLineClosure());
        } else {
            $this->error(sprintf('File [%s] not found.', $mainFile));
            return -1;
        }

        if ($exampleContents = @file_get_contents($exampleFile)) {
            $exampleCollection = collect(explode($lineSeparator, $exampleContents));
            $exampleEnvKeys = $exampleCollection->filter($this->filterEmptyLineClosure())->map(function ($line) {
                return explode('=', $line)[0];
            });
        } else {
            $this->error(sprintf('File [%s] not found.', $exampleFile));
            return -2;
        }

        $diffEnvKeys = $exampleEnvKeys->diff($mainEnvKeys);
        $diffLines = $diffEnvKeys->map(function ($diffEnvKey) use ($exampleCollection) {
            return $exampleCollection->first(function ($exampleLine) use ($diffEnvKey) {
                return str_is($diffEnvKey . '*', $exampleLine);
            });
        });

        if ($diffLines->count() > 0) {
            $this->info(sprintf('An undefined environment variable was found in file [%s].', $mainFile));
            $diffLines->each(function ($diffLine) {
                $this->line($diffLine);
            });

            if ($this->confirm(sprintf('Do you want to add the above environment variables to file [%s]?（Append to the end）', $mainFile))) {
                $backupFile = $mainFile . '.back';
                file_put_contents($backupFile, $mainContents);
                $now = now();
                $newMainContents = $mainContents . $lineSeparator . $this->appendPrefix($now) . $diffLines->implode($lineSeparator) . $lineSeparator . $this->appendSuffix($now);
                file_put_contents($mainFile, $newMainContents);
                $this->info(sprintf('Environment variables have been added to file [%s]. Also, a backup file [%s] has been created.', $mainFile, $backupFile));
            }
        } else {
            $this->line('There were no undefined environment variables.');
        }

        $this->line('=== EXIT ===');
    }

    private function filterEmptyLineClosure()
    {
        return function ($line) {
            return strlen($line) > 0;
        };
    }

    private function appendPrefix($date){
        return sprintf("# === [%s] dotenv:check コマンドにより自動的に追加 ここから ===\n", $date);
    }

    private function appendSuffix($date){
        return sprintf("# === [%s] dotenv:check コマンドにより自動的に追加 ここまで ===\n", $date);
    }
}
