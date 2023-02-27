<?php

namespace superwave\migrate;

use superwave\migrate\commands\Make;
use superwave\migrate\commands\Run;
use Symfony\Component\Console\Application as App;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Phinx console application.
 *
 * @author Rob Morgan <robbym@gmail.com>
 */
class Application extends App
{
    /**
     * Initialize the Phinx console application.
     */
    public function __construct()
    {
        parent::__construct('superwave php server database migrate tool');

        $this->addCommands([
            new Make(),
            new Run(),
        ]);
    }

    /**
     * Runs the current application.
     *
     * @param  InputInterface  $input  An Input instance
     * @param  OutputInterface  $output  An Output instance
     *
     * @return int 0 if everything went fine, or an error code
     * @throws Throwable
     */
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        // always show the version information except when the user invokes the help
        // command as that already does it
        if ($input->hasParameterOption('--no-info') === false) {
            if (($input->hasParameterOption(['--help', '-h']) !== false) || ($input->getFirstArgument() !== null && $input->getFirstArgument(
                    ) !== 'list')) {
                $output->writeln($this->getLongVersion());
                $output->writeln('');
            }
        }

        return parent::doRun($input, $output);
    }
}
