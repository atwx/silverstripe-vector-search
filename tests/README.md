# Tests

The suite needs a real MariaDB 11.8 server: most of it asserts what the database actually
does with `VECTOR` columns, vector indexes and the `VEC_*` functions, rather than
asserting SQL strings. No test reaches an embeddings API - `StubEmbeddingsProvider` is
registered for the whole run, and the provider tests drive `openai-php/client`'s own fake.

## Running them

In a checkout of this module:

```bash
composer install
SS_DATABASE_CLASS=MySQLDatabase \
SS_DATABASE_SERVER=127.0.0.1 \
SS_DATABASE_USERNAME=root \
SS_DATABASE_PASSWORD=root \
SS_DATABASE_NAME=ss_vector_search \
vendor/bin/phpunit
```

The account needs `CREATE DATABASE`: every test class runs against its own temporary
database.

Set `SS_PHPUNIT_FLUSH=1` after adding or renaming a test class, or Silverstripe's cached
class manifest will not know about it (`getItemPath returned null for ...`).

Inside a project that has this module in `vendor/`, point PHPUnit at the module instead:

```bash
vendor/bin/phpunit vendor/atwx/silverstripe-vector-search/tests/php \
  --bootstrap vendor/silverstripe/framework/tests/bootstrap.php
```

## In CI

[`.github/workflows/ci.yml`](../.github/workflows/ci.yml) runs the same thing against a
MariaDB service container. It also runs locally with [act](https://github.com/nektos/act):

```bash
act push -j tests
```

The job deliberately runs inside a `php:*-cli` container so the service is reachable as
`mariadb` under both runners - on GitHub a non-container job would see it on `127.0.0.1`,
under act it stays on the container network.

## Layout

| File | Covers |
| --- | --- |
| `FuncTest.php` | the `Func` compiler: placeholders, nesting, parameter order, backend binding |
| `VectorFromTest.php` | `VectorFrom::single()` / `::batch()` and the `VEC_FromText(?)` fragment |
| `DBVectorTest.php` | the `Vector` field: column spec, index spec, validation, write manipulation |
| `VectorWriteTest.php` | writing vectors through the ORM and reading them back out of MariaDB |
| `VSearchTest.php` | `annotateQuery()` / `sortByDistance()` against real distance functions |
| `MSchemaManagerTest.php` | vector index reporting, option parsing, and `db:build` idempotency |
| `OpenAIEmbeddingsProviderTest.php` | the default provider, against a faked HTTP client |
| `Stub/` | the fake provider, the fixture DataObjects and an instrumented schema manager |
