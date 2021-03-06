<?php

namespace CodingAvenue\Proof\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use CodingAvenue\Proof\BinFinder;
use CodingAvenue\Proof\Config;
use CodingAvenue\Proof\CLI\AnswerFileFinder;
use CodingAvenue\Proof\CLI\ProvisionIdFinder;

class ProofRunner extends Command
{
    /**
     * Configure the arguments and options
     */
    protected function configure()
    {
        $this
            ->setName("codingavenue:proof-runner")
            ->setDescription("Test Runner command, allows authors to create a test code to used by their proof files")
            ->addArgument('files', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'A list of proof files to run by the Test Runner.');
    }

    /**
     * Run the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $files = $input->getArgument('files');
        if ($input->getOption('verbose')) {
            $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        }

        $output->writeln("Loading configuration file", OutputInterface::VERBOSITY_VERBOSE);

        $config = new Config();
        $binFinder = new BinFinder($config);

        $output->writeln("Configuration file loaded", OutputInterface::VERBOSITY_VERBOSE);
        $output->writeln("Preparing files to be executed by phpunit", OutputInterface::VERBOSITY_VERBOSE);

        foreach ($files as $file) {
            $output->writeln("Preparing file {$file}", OutputInterface::VERBOSITY_VERBOSE);
            $output->writeln("Resolving test file code to be used on this test.", OutputInterface::VERBOSITY_VERBOSE);

            $answerFileFinder = new AnswerFileFinder();
            $answerFile = $answerFileFinder->resolve($file);

            $output->writeln("Using test file {$answerFile} for this test as code input.", OutputInterface::VERBOSITY_VERBOSE);
            $output->writeln("Copying content to ./code", OutputInterface::VERBOSITY_VERBOSE);

            $queryFile = getcwd() . "/code";

            $fileHandler = fopen($queryFile, 'w');
            fwrite($fileHandler, file_get_contents($answerFile));
            fclose($fileHandler);

            $provisionFinder = new ProvisionIdFinder();
            $databaseId = $provisionFinder->resolve($file);

            $output->writeln("Copy completed.", OutputInterface::VERBOSITY_VERBOSE);
            $output->writeln("Preparing bootstrap file", OutputInterface::VERBOSITY_VERBOSE);

            $bootstrapFile = "./bootstrap.php";

            $boot = <<<EOF
<?php
\$code = '$queryFile';
\$id = '$databaseId';
EOF;

            $bootHandler = fopen($bootstrapFile, 'w');
            fwrite($bootHandler, $boot);
            fclose($bootHandler);

            $out = array();
            $phpUnit = $binFinder->getPHPUnit();
            $output->writeln("Running command `{$phpUnit} --verbose --tap {$file}`", OutputInterface::VERBOSITY_VERBOSE);

            exec("{$phpUnit} --verbose --bootstrap {$bootstrapFile} --tap {$file}", $out);

            foreach ($out as $line) {
                $output->writeln($line);
            }

            unlink($queryFile);
            unlink($bootstrapFile);
        }
    }
}
