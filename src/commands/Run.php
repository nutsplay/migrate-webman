<?php

namespace superwave\migrate\commands;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use  Illuminate\Database\Capsule\Manager as Db;
use Throwable;

class Run extends Command
{
    protected static $defaultName        = 'run';
    protected static $defaultDescription = '运行数据迁移脚本';

    protected const MIGRATION_TABLE = 'migrations';

    protected function configure()
    {
        $this->addArgument('db', InputArgument::REQUIRED, '要执行的数据库,多个数据库用","隔开', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $finder = new Finder();
        $finder->in(MIGRATION_DIR)->files()->name('*.sql')->sortByName();
        $migration_files[] = [];
        foreach ($finder as $file) {
            $prefix                     = Str::before($file->getFilename(), '-');
            $migration_files[$prefix][] = $file;
        }
        $dbs = explode(',', $input->getArgument('db'));
        foreach ($migration_files as $prefix => $files) {
            if (empty($files)) {
                continue;
            }
            foreach ($dbs as $database) {
                if (!Str::contains($database, $prefix)) {
                    continue;
                }
                $output->writeln(sprintf('开始执行数据库迁移[%s]', $database));
                if (!Db::schema($database)->hasTable(self::MIGRATION_TABLE)) {
                    $output->writeln(sprintf('初始化数据库迁移表[%s]', $database));
                    Db::schema($database)->create(self::MIGRATION_TABLE, function (Blueprint $blueprint) {
                        $blueprint->id();
                        $blueprint->string('migration')->comment('迁移文件');
                    });
                }
                $migrations = Db::connection($database)
                    ->table(self::MIGRATION_TABLE)
                    ->get(['id', 'migration'])
                    ->pluck('id', 'migration');
                try {
                    Db::connection($database)->getPdo()->setAttribute(\PDO::ATTR_AUTOCOMMIT, 0);
                    Db::connection($database)->beginTransaction();
                    /** @var SplFileInfo $file */
                    foreach ($files as $file) {
                        if (!$migrations->has($file->getFilename())) {
                            $time = time();
                            Db::connection($database)->getPdo()->exec($file->getContents());
                            Db::connection($database)->insert(sprintf('insert into %s value (null,?)', self::MIGRATION_TABLE), [$file->getFilename()]
                            );
                            $output->writeln(sprintf('脚本:%s,耗时:%s', $file->getFilename(), time() - $time));
                        }
                    }
                    Db::connection($database)->commit();
                } catch (Throwable $exception) {
                    Db::connection($database)->rollBack();
                    $output->writeln($exception->getMessage());
                    return self::FAILURE;
                }
                $output->writeln(sprintf('结束执行数据库迁移[%s]', $database));
            }
        }
        return self::SUCCESS;
    }
}