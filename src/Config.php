<?php

namespace Eril\DbTbl;

use Eril\DbTbl\Cli\CliPrinter;
use Symfony\Component\Yaml\Yaml;
use Exception;

class Config
{
    private array $config = [];
    private string $configFile;
    private bool $isNew = false;
    private ?string $customOutputFile = null;

    public function __construct(?string $configFile = null)
    {
        $this->configFile = $configFile ?: getcwd() . '/dbtbl.yaml';
        $this->load();
    }

    // ------------------------------------------------------------------
    // Bootstrapping
    // ------------------------------------------------------------------

    private function load(): void
    {
        if (!file_exists($this->configFile)) {
            $this->isNew = true;
            $this->createCleanTemplate();
            return;
        }

        try {
            $yamlConfig = Yaml::parseFile($this->configFile);

            $this->config = array_replace_recursive(
                $this->defaultConfig(),
                $yamlConfig ?? []
            );

            $this->validate();
            $this->isNew = false;
        } catch (Exception $e) {
            throw new Exception(
                "Error loading config file '{$this->configFile}': " . $e->getMessage()
            );
        }

        $this->runAutoloaders();
    }

    private function defaultConfig(): array
    {
        return [
            'include' => null,

            'database' => [
                'driver' => 'mysql',
                'connection' => null,
                'host' => 'localhost',
                'port' => 3306,
                'name' => '',
                'user' => 'root',
                'password' => '',
                'path' => 'database.sqlite',
            ],

            'output' => [
                'mode' => 'file',
                'path' => './',
                'namespace' => '',
                'naming' => [
                    'strategy' => 'full',
                    'abbreviation' => [
                        'max_length' => 15,
                        'dictionary_lang' => 'en',
                        'dictionary_path' => null,
                    ],
                ],
            ],
        ];
    }

    private function validate(): void
    {
        $this->validateOutput();
    }

    private function validateOutput(): void
    {
        $mode = $this->getOutputMode();
        $namespace = $this->getOutputNamespace();

        if (!in_array($mode, ['file', 'psr4'], true)) {
            throw new Exception(
                "Invalid output.mode '{$mode}'. Allowed values: file, psr4."
            );
        }

        if ($mode === 'psr4' && $namespace === '') {
            throw new Exception(
                "output.namespace is required when output.mode is 'psr4'."
            );
        }
        
    }

    // ------------------------------------------------------------------
    // YAML Template
    // ------------------------------------------------------------------

    private function createCleanTemplate(): void
    {
        $template = <<<YAML
# ------------------------------------------------------------
# db-tbl configuration file
#
# Auto-generated on first run.
# Delete this file to regenerate a clean template.
# ------------------------------------------------------------

# Optional: manually include a PHP file before execution
include: null

# ------------------------------------------------------------
# Database configuration
# ------------------------------------------------------------
database:

  # Optional custom connection resolver
  # Must return a PDO instance
  # Example: 'App\\\\Database::getConnection'
  # connection: null

  driver: mysql            # mysql | pgsql | sqlite

  # For MySQL / PostgreSQL
  host: env(DB_HOST)       # default: localhost
  port: env(DB_PORT)       # default: 3306
  name: env(DB_NAME)       # required
  user: env(DB_USER)       # default: root
  password: env(DB_PASS)  # default: empty

  # SQLite only
  # path: env(DB_PATH)     # e.g. database.sqlite

# ------------------------------------------------------------
# Output configuration
# ------------------------------------------------------------
output:

  # Output mode:
  # - file  → generate all classes into one file
  # - psr4  → generate one class per table (PSR-4)
  mode: file

  # Base output directory (always a directory)
  path: "./"

  # REQUIRED for psr4 mode
  namespace: ""

  # Naming rules
  naming:
    strategy: full          # full | short

    abbreviation:
      max_length: 15        # maximum length of generated names
      dictionary_lang: en   # en | pt | es | all
      dictionary_path: null # custom dictionary file (optional)
YAML;

        $dir = dirname($this->configFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_put_contents($this->configFile, $template) === false) {
            throw new Exception("Cannot create config file: {$this->configFile}");
        }

        $this->config = Yaml::parse($template);
        $this->isNew = true;
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    public function isNew(): bool
    {
        return $this->isNew;
    }

    public function save(): void
    {
        // Se for novo template, não sobrescreve comentários
        if ($this->isNew) {
            return;
        }

        $yaml = Yaml::dump($this->config, 4, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);

        $dir = dirname($this->configFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_put_contents($this->configFile, $yaml) === false) {
            throw new Exception("Cannot write config file: " . $this->configFile);
        }
    }

    public function exposeYaml()
    {
        $yaml = Yaml::dump($this->config, 4, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
        return $yaml;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->config;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $this->resolveEnvVars($value);
    }

    public function set(string $key, mixed $value): self
    {
        $config = &$this->config;

        foreach (explode('.', $key) as $segment) {
            if (!isset($config[$segment]) || !is_array($config[$segment])) {
                $config[$segment] = [];
            }
            $config = &$config[$segment];
        }

        $config = $value;
        return $this;
    }

    // ------------------------------------------------------------------
    // Output helpers
    // ------------------------------------------------------------------

    public function getOutputMode(): string
    {
        return (string) $this->get('output.mode', 'file');
    }

    public function getOutputPath(): string
    {
        return rtrim((string) $this->get('output.path', './'), '/') . '/';
    }

    public function getOutputNamespace(): string
    {
        return trim((string) $this->get('output.namespace'));
    }

    public function getOutputFile(string $default = 'Tbl.php'): string
    {
        if ($this->customOutputFile !== null) {
            return $this->getOutputPath() . $this->customOutputFile;
        }

        return $this->getOutputPath() . $default;
    }

    public function setOutputFile(string $filename): self
    {
        $this->customOutputFile = $filename;
        return $this;
    }

    public function resetOutputFile(): self
    {
        $this->customOutputFile = null;
        return $this;
    }

    // ------------------------------------------------------------------
    // Database helpers
    // ------------------------------------------------------------------

    public function getDatabaseName(): string
    {
        return (string) $this->get('database.name', '');
    }

    public function getDriver(): string
    {
        return (string) $this->get('database.driver', 'mysql');
    }

    public function hasConnectionCallback(): bool
    {
        return !empty($this->get('database.connection'));
    }

    public function getConnectionCallback(): ?callable
    {
        $callback = $this->get('database.connection');
        if (!$callback) {
            return null;
        }

        if (is_string($callback)) {
            if (str_contains($callback, '::')) {
                [$class, $method] = explode('::', $callback, 2);
                return fn () => $class::$method();
            }

            if (function_exists($callback)) {
                return $callback;
            }
        }

        throw new Exception("Invalid database.connection callback");
    }

    // ------------------------------------------------------------------
    // Naming configuration (mantido para compatibilidade)
    // ------------------------------------------------------------------

    public function getNamingConfig(): array
    {
        return [
            'table' => $this->get('output.naming.strategy', 'full'),
            'column' => $this->get('output.naming.strategy', 'full'),
            'foreign_key' => $this->get('output.naming.strategy', 'full'),
            'abbreviation' => [
                'dictionary_path' => $this->get('output.naming.abbreviation.dictionary_path'),
                'dictionary_lang' => $this->get('output.naming.abbreviation.dictionary_lang', 'en'),
                'max_length' => $this->get('output.naming.abbreviation.max_length', 15),
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Utilities
    // ------------------------------------------------------------------

    private function resolveEnvVars(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        if (preg_match('/^env\(([^)]+)\)$/', $value, $m)) {
            return getenv($m[1]) ?: $value;
        }

        if (preg_match('/^\${([^}]+)}$/', $value, $m)) {
            return getenv($m[1]) ?: $value;
        }

        if (preg_match('/^[A-Z_][A-Z0-9_]*$/', $value)) {
            return getenv($value) ?: $value;
        }

        return $value;
    }

    private function runAutoloaders(): void
    {
        $file = $this->get('include');

        if ($file && is_file($file)) {
            include_once $file;
            CliPrinter::info("file included: $file");
        }
    }

    public function getConfigFile(): string
    {
        return $this->configFile;
    }

    public function getConfigFileName(): string
    {
        return basename($this->configFile);
    }
}