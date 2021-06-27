<?php declare(strict_types=1);

namespace Uploadcare\WpMigrate\Command;

use Doctrine\DBAL\Driver\{Connection, Exception as DriverException};
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Statement;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\{Command\Command, Exception\RuntimeException, Input\InputArgument, Input\InputInterface, Output\OutputInterface};
use Uploadcare\{Api, Configuration, Interfaces\File\FileInfoInterface, Interfaces\UploaderInterface};

class MigrateCommand extends Command
{
    protected static $defaultName = 'migrate';

    /**
     * @var string|null
     */
    private $databasePrefix = null;

    /**
     * @var Connection|null
     */
    private $connection = null;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var UploaderInterface
     */
    private $uploader;

    public function __construct(LoggerInterface $consoleLogger, string $name = null)
    {
        parent::__construct($name);
        $this->logger = $consoleLogger;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('dbname', InputArgument::REQUIRED, 'Database name')
            ->addArgument('db_username', InputArgument::REQUIRED, 'User for database')
            ->addArgument('db_password', InputArgument::REQUIRED, 'Database user password')
            ->addArgument('uploadcare_public_key', InputArgument::REQUIRED, 'Uploadcare public key')
            ->addArgument('uploadcare_secret_key', InputArgument::REQUIRED, 'Uploadcare secret key')
            ->addArgument('table_prefix', InputArgument::OPTIONAL, 'Table prefix')
            ->addArgument('db_host', InputArgument::OPTIONAL, 'Database host', 'localhost')
            ->addArgument('db_port', InputArgument::OPTIONAL, 'Database port', '3306')
            ->addArgument('db_driver', InputArgument::OPTIONAL, 'Database driver', 'pdo_mysql')
        ;
    }

    /**
     * @psalm-suppress PossiblyInvalidPropertyAssignmentValue
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);

        $conf = Configuration::create($input->getArgument('uploadcare_public_key'), $input->getArgument('uploadcare_secret_key'));
        $this->uploader = (new Api($conf))->uploader();

        $this->databasePrefix = $input->getArgument('table_prefix');
        $this->publicKey = $input->getArgument('uploadcare_public_key');
        try {
            $this->connection = $this->makeConnection($input);
            $this->testConnection();
        } catch (DbalException | DriverException $e) {
            throw new RuntimeException('Unable to connect to database', (int) $e->getCode(), $e);
        }
        $this->transfer();

        return 0;
    }

    private function transfer(): void
    {
        $statement = $this->getAttachments();
        foreach ($statement->executeQuery() as $item) {
            $id = $item['ID'] ?? null;
            $guid = $item['guid'] ?? null;

            if ($id === null || $guid === null) {
                $this->logger->warning('Item cannot be transferred', $item);
                continue;
            }

            $meta = $this->getPostMeta((int) $id);
            $uuid = $meta['uploadcare_uuid']['meta_value'] ?? null;

            if (!empty($uuid)) {
                $this->logger->info(\sprintf('Seems like Post \'%s\' already transferred. Uploadcare UUID is %s, skipping', $id, $uuid));
                continue;
            }

            $file = $this->toUploadcare($guid);
            if (!$file instanceof FileInfoInterface) {
                continue;
            }

            $this->insertMeta((int) $id, $file->getOriginalFileUrl(), $file->getUuid());
        }
    }

    private function insertMeta(int $postId, string $url = null, string $uuid = null): void
    {
        $tn = $this->getTableName('postmeta');
        $metas = [
            '_wp_attached_file' => $url,
            'uploadcare_url' => \pathinfo($url, PATHINFO_DIRNAME) . '/',
            'uploadcare_uuid' => $uuid,
            'uploadcare_url_modifiers' => null,
        ];

        foreach ($metas as $metaKey => $value) {
            $metaId = $this->getMetaId($postId, $metaKey);
            if ($metaId !== null) {
                $sql = \sprintf('UPDATE %s SET meta_value=:val WHERE meta_id=:id', $tn);
                $q = $this->connection->prepare($sql);
                $q->bindValue('id', $metaId);

                $this->logger->info(\sprintf('Update meta \'%s\' id %d with value \'%s\' with sql %s', $metaKey, $metaId, $value ?? 'null', $sql));
            } else {
                $sql = \sprintf('INSERT INTO %s VALUES (null, :post_id, :key, :val)', $tn);
                $q = $this->connection->prepare($sql);
                $q->bindValue('key', $metaKey);
                $q->bindValue('post_id', $postId);

                $this->logger->info(\sprintf('Insert meta \'%s\' with post_id %d and value \'%s\' with sql %s', $metaKey, $postId, $value ?? 'null', $sql));
            }
            $q->bindValue('val', $value);

            try {
                $count = $q->executeStatement();
                $this->logger->info(\sprintf('Updated %d rows', $count));
            } catch (\Throwable $e) {
                $this->logger->error($e->getMessage());
            }
        }
    }

    private function getMetaId(int $postId, string $metaKey): ?int
    {
        $q = $this->connection->prepare(\sprintf('SELECT meta_id FROM %s WHERE post_id=:id AND meta_key=:key', $this->getTableName('postmeta')));
        $q->bindValue('id', $postId);
        $q->bindValue('key', $metaKey);
        $resultSet = $q->executeQuery();

        $result = $resultSet->fetchOne();
        if ($result !== false) {
            return (int) $result;
        }

        return null;
    }

    private function toUploadcare(string $url): ?FileInfoInterface
    {
        try {
            return $this->uploader->fromUrl($url);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
        }

        return null;
    }

    private function getPostMeta(int $postId): array
    {
        $q = $this->connection->prepare(\sprintf('SELECT * FROM %s WHERE post_id=:id', $this->getTableName('postmeta')));
        $q->bindValue('id', $postId);

        $result = [];
        foreach ($q->executeQuery() as $item) {
            if (($key = ($item['meta_key'] ?? null)) === null) {
                continue;
            }

            $result[$key] = $item;
        }

        return $result;
    }

    /**
     * @throws DriverException
     */
    private function testConnection(): void
    {
        $q = $this->getAttachments();
        $q->executeQuery();

        $count = $q->rowCount();

        $this->logger->info(\sprintf('Attachments count: \'%d\'', $count));
    }

    private function getAttachments(string $select = '*'): Statement
    {
        $q = $this->connection->prepare(\sprintf('SELECT %s FROM %s WHERE post_type=:pt', $select, $this->getTableName('posts')));
        $q->bindValue('pt', 'attachment');
        if (!$q instanceof Statement) {
            throw new RuntimeException('Wrong result from query. Call to support');
        }

        return $q;
    }

    private function getTableName(string $name): string
    {
        if ($this->databasePrefix !== null) {
            return \sprintf('%s%s', $this->databasePrefix, $name);
        }

        return $name;
    }

    /**
     * @param InputInterface $input
     * @return Connection
     * @throws DbalException
     * @psalm-suppress PossiblyInvalidArgument
     * @psalm-suppress PossiblyInvalidCast
     */
    private function makeConnection(InputInterface $input): Connection
    {
        return DriverManager::getConnection([
            'dbname' => $input->getArgument('dbname'),
            'user' => $input->getArgument('db_username'),
            'password' => $input->getArgument('db_password'),
            'host' => $input->getArgument('db_host'),
            'port' => (string) $input->getArgument('db_port'),
            'driver' => $input->getArgument('db_driver'),
        ]);
    }
}
