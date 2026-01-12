# db-tbl

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892BF)](https://www.php.net/) [![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE) [![Version](https://img.shields.io/github/v/release/erilshackle/db-tbl-php)](https://github.com/erilshackle/db-tbl-php/releases)

`db-tbl` gera automaticamente **classes PHP** a partir do schema **da** base de dados, criando constantes para tabelas, colunas, enums e foreign keys. Facilita o desenvolvimento com **referência direta e type-safe** à base de dados.

---

## ✨ Características

* Geração automática de classes PHP a partir do schema
* Modos de saída: `file` (um arquivo) ou `psr4` (uma classe por tabela)
* Suporte MySQL, PostgreSQL e SQLite
* Alias automáticos para tabelas
* CLI simples para gerar, sincronizar e verificar schema
* Compatível com PSR-4 e autoload moderno

---

## 📦 Instalação

```bash
composer require eril/db-tbl
```

---

## ⚙️ Configuração

Na primeira execução, um arquivo `dbtbl.yaml` será criado:

```yaml
database:
  driver: mysql            # mysql | pgsql | sqlite
  host: env(DB_HOST)
  port: env(DB_PORT)
  name: env(DB_NAME)
  user: env(DB_USER)
  password: env(DB_PASS)

output:
  mode: file               # file | psr4
  path: "./"
  namespace: ""            # obrigatório no modo psr4

naming:
  strategy: full           # full | short
  abbreviation:
    max_length: 15
    dictionary_lang: en
```

> **Nota:** `namespace` é obrigatório apenas no modo `psr4`.

---

## 🛠️ Uso via CLI

```bash
# Gerar classes no modo padrão (file)
php vendor/bin/db-tbl

# Gerar em modo PSR-4
php vendor/bin/db-tbl --psr4

# Verificar mudanças no schema
php vendor/bin/db-tbl --check

```

---

## 📁 Estrutura de saída

### Modo `file` (padrão)

Gera um único arquivo `Tbl.php`:

```php
final class Tbl {
    public const users = 'users';
    public const as_users = 'users u';
}

/** `table: users` (alias: `u`) */
final class TblUsers {
    public const __table = 'users';
    public const __alias = 'users u';

    public const id = 'id';
    public const name = 'name';
    public const status = 'status';

    public const enum_status_active = 'active';
    public const enum_status_inactive = 'inactive';

    /** references `roles` → `id` */
    public const fk_roles = 'role_id';
}
```

### Modo `psr4`

Gera múltiplos arquivos:

```
src/Database/
├── Tbl.php
├── TblUsers.php
├── TblRoles.php
└── TblProducts.php
```

---

## 🔌 Integração com Composer

```json
{
  "autoload": {
    "psr-4": { "App\\Database\\": "src/Database/" },
    "files": [ "src/Database/Tbl.php" ]
  }
}
```

```bash
composer dump-autoload
```

No código:

```php
use App\Database\Tbl;
use App\Database\TblUsers;

echo Tbl::users;                   // 'users'
echo TblUsers::id;                 // 'id'
echo TblUsers::enum_status_active; // 'active'
```

---

## 💻 Exemplo de query usando constantes

```php
$sql = sprintf(
    "SELECT u.%s, r.%s FROM %s u JOIN %s r ON u.%s = r.%s WHERE u.%s = ?",
    TblUsers::name,
    TblRoles::name,
    Tbl::as_users,
    Tbl::as_roles,
    TblUsers::fk_roles,
    TblRoles::id,
    TblUsers::status
);
```

---

## 🚨 Limitações e cuidados

* Arquivos gerados **não devem ser editados manualmente**
* `output.namespace` é obrigatório no modo PSR-4
* Diretório de saída precisa de permissão de escrita
* Classes existentes serão sobrescritas
* Hash do schema é baseado na estrutura, **não nos dados**

---

## 🌐 Conexão customizada

```yaml
database:
  connection: 'App\\Database\\Connection::getPdo'
```

O método deve retornar uma instância de `PDO`.

---

## 🧩 Abreviações e nomenclatura

```yaml
naming:
  strategy: short
  abbreviation:
    max_length: 15
    dictionary_lang: pt
```

Exemplo de dicionário customizado (`PHP`):

```php
return = [
    "users": "usr",
    "products": "prod"
];
```

---

## 🤝 Contribuição

1. Fork e clone
2. Crie branch: `git checkout -b minha-feature`
3. Commit: `git commit -am 'Add feature'`
4. Push e PR

---

## 📄 Licença

MIT License. Veja [LICENSE](LICENSE).

