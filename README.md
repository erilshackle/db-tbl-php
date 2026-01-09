# db-tbl

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF)](https://www.php.net/) [![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE)


`db-tbl` é uma biblioteca PHP para gerar classes baseadas no schema do banco de dados, incluindo:

* Constantes globais para tabelas (`Tbl`)
* Classes de tabela (`TblNomeDaTabela`) com colunas, enums e foreign keys
* Suporte a **MySQL**, **PostgreSQL** e **SQLite**
* Gerenciamento automático de aliases e abreviações de nomes
* CLI para gerar e sincronizar classes (`db-tbl`)

Tudo isso sem precisar escrever manualmente código de mapeamento, útil para projetos que precisam de referência constante ao banco em PHP.

---

## Instalação

Instale via Composer:

```bash
composer require eril/db-tbl
```
---

## Configuração

O arquivo principal de configuração é `dbtbl.yaml`, gerado automaticamente na primeira execução:

```yaml
# Autoload a file
include: null

database:
  driver: mysql        # mysql, pgsql, sqlite
  host: env(DB_HOST)   # ou localhost
  port: env(DB_PORT)   # 3306
  name: env(DB_NAME)
  user: env(DB_USER)
  password: env(DB_PASS)

output:
  path: "./"           # diretório de saída
  namespace: ""        # namespace opcional
  naming:
    strategy: "full"
    abbreviation:
      dictionary_lang: "en"
      dictionary_path: null
      max_length: 15
```

* **driver**: banco de dados a ser lido
* **output.path**: onde os arquivos PHP gerados serão salvos
* **naming.strategy**: estratégia de nomes para constantes (`full` ou `short`)

---

## CLI

### Gerar classes

```bash
php vendor/bin/db-tbl
```

Isso irá gerar os arquivos no diretório configurado (`output.path`).

### Checar mudanças no schema e regenerar (`--check`)

```bash
php vendor/bin/db-tbl --check
```

* Compara o hash do schema atual com o último gerado
* Se houver mudanças, recria os arquivos

> ⚠ O check é feito com base no arquivo gerado então evite mover e arquivo gerado ou alterar-lo depois de ser gerado.

### Especificar diretório de saída

```bash
php vendor/bin/db-tbl ./src/Database
```

* Substitui o path do `output.path` do YAML temporariamente



### Comandos

```bash
php vendor/bin/db-tbl --help
```

---

## Estrutura de arquivos gerados

* `Tbl.php` → classe global `Tbl` com todas as tabelas como constantes
* `TblNomeDaTabela.php` → classe de cada tabela com:

  * Colunas como constantes
  * ENUMs como constantes
  * Foreign keys comentadas

Exemplo:

```php
class Tbl
{
    public const users = 'users';
    public const roles = 'roles';
    public const as_users = 'users u';
    public const as_roles = 'roles r';
}

class TblUser
{
    public const id = 'id';
    public const name = 'name';
    public const email = 'email';

    public const enum_status = 'active';

    /* FK → `roles`.`id` */ 
    public const fk_roles = 'role_id';

    public const _TABLE = 'users';
    public const _ALIAS = 'usr';
}
```

---

## Integração com Composer

Adicione o arquivo gerado ao autoload:

```json
"autoload": {
    "files": [
        "includes/Tbl.php"
    ]
}
```

Depois, rode:

```bash
composer dump-autoload
```

Agora você pode usar as constantes globalmente:

```php
echo Tbl::users;           // users
echo TblUsers::name;        // name
```

---

## Integração no projeto

```php
require 'vendor/autoload.php';

use Eril\DbTbl\Generators\TblClassesGenerator;
use Eril\DbTbl\Config;
use Eril\DbTbl\Resolvers\ConnectionResolver;

$config = new Config();
$pdo = ConnectionResolver::fromConfig($config);
$generator = new TblClassesGenerator($schemaReader, $config);
$generator->run();
```

---

## Compatibilidade

* PHP 8.1+
* Banco de dados suportados:

  * MySQL / MariaDB
  * PostgreSQL
  * SQLite

---

## Dicionários e abreviações

* Nomes de tabelas e colunas podem ser abreviados automaticamente
* Pode usar dicionário customizado via `output.naming.abbreviation.dictionary_path`
* Suporta linguagens: `en`, `pt` ou `all`

---

## Contribuição

1. Clone o repositório
2. Rode `composer install`
3. Faça suas alterações e testes
4. Crie PR para revisão

---

## Licença

MIT License.
