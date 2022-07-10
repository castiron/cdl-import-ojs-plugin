<?php

namespace CdlImportPlugin\Commands;

/**
 * @psalm-suppress UndefinedFunction
 */
import('classes.journal.JournalDAO');

/**
 * @psalm-suppress UndefinedFunction
 */
import('classes.issue.IssueDAO');

/**
 * @psalm-suppress UndefinedFunction
 */
import('classes.article.PublishedArticleDAO');

class Cc
{
    /**
     * @var boolean
     */
    protected $dryRun = false;

    /**
     * @var boolean
     */
    protected $verbose = false;

    /**
     * @var array
     */
    protected $stats = [];

    /**
     * @var string[]
     */
    protected static $statKeys = [
        'NO_LICENSE_PROVIDED' => 'Import data missing identifiable license',
        'JOURNAL_PATH_NOT_FOUND' => 'Journal not found with with provided in import data',
        'ISSUE_NOT_FOUND' => 'A single issue could not be identified',
        'OK' => 'Everything went well!'
    ];

    /**
     * These are boolean flags only
     * @var array[]
     */
    protected static $flags = [
        ['dry-run', 'd', 'Does not write to database'],
        ['verbose', 'v', 'Verbose']
    ];

    /**
     *
     */
    public function help(): void
    {
        echo PHP_EOL . 'Displaying help' . PHP_EOL;
    }

    /**
     * @param $args
     */
    public function validate(array $args): bool
    {
        $filename = @array_values($this->getNonFlagArgs($args))[0];
        if (is_null($filename)) {
            throw new \Exception('Filename must be passed as first argument');
        }

        // Dealing with boolean flags
        $flags = $this->getFlagArgs($args);
        if (count($flags) > 0) {
            $allowedFlags = [];
            foreach (self::$flags as $allowedFlag) {
                $varName = $this->getVarName($allowedFlag[0]);
                $allowedFlags['--' . $allowedFlag[0]] = $varName;
                $allowedFlags['-' . $allowedFlag[1]] = $varName;
            }
            foreach ($flags as $flag) {
                if (!array_key_exists($flag, $allowedFlags)) {
                    throw new \Exception('Unrecognized flag passed: ' . $flag);
                }
                $varName = $allowedFlags[$flag];
                $this->$varName = true;
            }
        }
        return true;
    }

    /**
     * Naive kebab to camel
     * @param $flag
     * @return mixed
     */
    protected function getVarName(string $flag): string
    {
        $str = str_replace('-', '', ucwords($flag, '-'));
        return lcfirst($str);
    }

    /**
     * @param $args
     * @return mixed
     */
    public function getFlagArgs(array $args): array
    {
        return array_filter(
            $args,
            function ($a) {
                return $a[0] === '-';
            }
        );
    }

    /**
     * @param $args
     * @return mixed
     */
    public function getNonFlagArgs(array $args): array
    {
        return array_filter(
            $args,
            function ($a) {
                return $a[0] !== '-';
            }
        );
    }

    /**
     * @param $args
     * @return bool
     */
    public function wantsHelp(array $args): bool
    {
        return @$args[0] === 'help';
    }

    /**
     * @param $args
     */
    public function execute(array $args): void
    {
        try {
            if ($this->wantsHelp($args) || !$this->validate($args)) {
                $this->help();
                return;
            }
            $this->executeImport($this->getNonFlagArgs($args));
        } catch (\Exception $e) {
            echo "Exception: " . $e->getMessage() . PHP_EOL;
            exit;
        }
    }

    /**
     * @param $filename
     * @return array
     */
    protected function getCsvFileData(string $filename): array
    {
        $rows = [];
        $headers = [];
        $fp = fopen($filename, 'r');
        while (!feof($fp)) {
            $line = fgets($fp, 2048);
            $rowData = str_getcsv($line, "\t");
            if (count($rowData) < 2) {
                continue;
            } // Skip incomplete rows
            if (count($headers) === 0) {
                $headers = $rowData;
            } else {
                $rows[] = array_combine($headers, $rowData);
            }
        }
        fclose($fp);

        return $rows;
    }

    /**
     * @param $rows
     * @return object[]
     */
    protected function cleanRawData(array $rows): array
    {
        $extractLicense = function (string $str): string {
            preg_match('/"(https:\/\/creativecommons.org\/[^"]+)"/', $str, $matches);
            return @$matches[1];
        };

        return array_map(
            function (array $row) use ($extractLicense) {
                return (object)[
                    'journal_path' => $row['unit_id'],
                    'issue_volume' => $row['volume'],
                    'issue_number' => $row['issue'],
                    'issue_publication_date' => $row['published'],
                    'license_url' => $extractLicense($row['attrs'])
                ];
            },
            $rows
        );
    }

    /**
     * @param $args
     */
    protected function executeImport(array $args): void
    {
        $filename = $args[0];
        if (!file_exists($filename)) {
            throw new \Exception('File doesn\'t exist: ' . $filename);
        }
        $betterData = $this->cleanRawData($this->getCsvFileData($filename));

        foreach ($betterData as $row) {
            $this->insertData($row);
        }

        $this->printStats();
    }

    /**
     * @param $row array
     * @return mixed
     */
    protected function formatIssueInfo(object $row): string
    {
        return sprintf('Journal: %s Issue: %s/%s Published: %s', ...array_values((array)$row));
    }

    /**
     * @param string $statKey
     * @param object $row
     */
    protected function log(string $statKey, object $row): void
    {
        $this->stat($statKey, $row);
        if (!$this->verbose) {
            return;
        }
        echo self::$statKeys[$statKey] . ' ' . $this->formatIssueInfo($row) . PHP_EOL;
    }

    /**
     *
     */
    protected function printStats(): void
    {
        $total = 0;
        echo '==========================================' . PHP_EOL . 'RESULTS' . PHP_EOL . '==========================================' . PHP_EOL;
        foreach (array_keys(self::$statKeys) as $statKey) {
            $number = array_key_exists($statKey, $this->stats) ? count($this->stats[$statKey]) : 0;
            $total += $number;
            echo $statKey . ': ' . $number . PHP_EOL;
        }
        echo 'TOTAL: ' . $total . PHP_EOL;
    }

    /**
     * @param $statKey
     * @param $row
     */
    protected function stat(string $statKey, object $row): void
    {
        if (!in_array($statKey, array_keys(self::$statKeys))) {
            throw new \Exception('Stat recorded for non-existent stat key: ' . $statKey);
        }
        if (!array_key_exists($statKey, $this->stats)) {
            $this->stats[$statKey] = [];
        }
        $this->stats[$statKey][] = $row;
    }

    /**
     *
     */
    protected function insertData(object $row): void
    {
        if (strlen($row->license_url) == 0) {
            $this->log('NO_LICENSE_PROVIDED', $row);
            return;
        }

        /**
         * @psalm-suppress UndefinedClass
         */
        $journal = (new \JournalDAO)->getJournalByPath($row->journal_path);
        if (is_null($journal)) {
            $this->log('JOURNAL_PATH_NOT_FOUND', $row);
            return;
        }

        /**
         * @psalm-suppress UndefinedClass
         */
        $issues = (new \IssueDAO)->getPublishedIssuesByNumber(
            $journal->getId(),
            $row->issue_volume,
            $row->issue_number
        )->toArray();
        if (count($issues) != 1) {
            $this->log('ISSUE_NOT_FOUND', $row);
            return;
        }

        /**
         * @psalm-suppress UndefinedClass
         */
        $articles = (new \PublishedArticleDAO)->getPublishedArticles($issues[0]->getId());

        // iterate the articles
        // upsert new field on the article_settings table


        $this->log('OK', $row);
    }
}