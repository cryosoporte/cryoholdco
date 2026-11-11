<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:

- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
    - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>

# Arquitectura y desarrollo de Crioholdco

## Alcance y criterio

- Antes de proponer un cambio, sigue el flujo desde la ruta hasta el controlador, los datos, la pantalla y las pruebas que correspondan. Revisa cómo resuelven responsabilidades similares los archivos vecinos.
- En una auditoría de solo lectura, cita el archivo y el método o componente, explica la dificultad concreta y propone el cambio mínimo. Indica qué comportamiento hay que conservar y cómo comprobarlo. Prioriza por beneficio y riesgo.
- Refactoriza un flujo a la vez. Justifica cada extracción por la claridad que aporta, la duplicación que elimina o el comportamiento que permite probar de forma aislada. El tamaño de un archivo es una señal para revisar sus responsabilidades.
- Aplica estas convenciones al código nuevo y a las partes que requiera la tarea. Evita renombres, movimientos, cambios de formato y limpiezas ajenas al flujo. Prefiere la estructura existente y nombres que describan la operación del negocio.

## Nuevas funcionalidades y módulos

- Identifica el flujo de usuario, quién puede ejecutarlo, qué datos necesita y qué resultado debe producir. Si amplía una responsabilidad de Clientes, Laboratorio o Serología, intégralo en ese módulo. Crea un módulo cuando el flujo tenga una responsabilidad de negocio propia.
- Para un flujo web, registra rutas con nombre en `routes/web.php`, en el grupo que corresponda. Aplica autenticación, autorización y alcance por empresa en Laravel según el flujo. Valida las entradas en el servidor y envía a la pantalla solo los datos que necesita.
- Sigue la estructura existente al crear una pantalla. Compón la página en `resources/js/pages`, reutiliza el layout adecuado y agrupa componentes y tipos propios en `resources/js/components/<modulo>`. Añade un acceso en la navegación correspondiente cuando el usuario deba poder llegar al módulo desde ahí.
- Para formularios y listados nuevos, define las props y los estados que necesita la pantalla, como carga, errores de validación y resultados vacíos. Reutiliza los componentes y patrones de interacción del módulo más cercano.
- Crea los archivos que requiera el flujo. Una ruta de consulta puede necesitar solo una acción y una pantalla. Añade un `FormRequest`, una clase de consulta o una operación de negocio cuando cumpla una de las responsabilidades descritas abajo.

## Laravel

- El controlador coordina la operación HTTP y construye la respuesta. Usa un FormRequest en app/Http/Requests cuando separar las reglas y mensajes de entrada permita leer mejor la acción o reutilizar la validación. Toma LoginRequest como referencia y conserva autorización, normalización, mensajes, redirecciones y respuestas de error. Las comprobaciones sobre el estado del negocio pertenecen al flujo que lo consulta o modifica.
- Mantén relaciones y consultas ligadas a un modelo cerca del modelo cuando encajen con sus responsabilidades. Si una consulta de listado reúne varios modelos y crece lo suficiente para dificultar la lectura del flujo, evalúa una clase de consulta con nombre específico. Conserva filtros, alcance por empresa, orden, paginación y forma de los resultados.
- Extrae una regla de negocio u operación de varios pasos cuando tenga una responsabilidad que se pueda nombrar y verificar por separado. Usa PlazoSerologia como ejemplo de una regla con nombre propio. Para lógica breve que solo sirve al flujo actual, considera mantenerla local. Evita agregar servicios o repositorios genéricos como requisito para cada controlador.
- Preserva las rutas y los contratos que consume el frontend, incluidas las props de Inertia, los estados HTTP y los mensajes. En operaciones de escritura o envío, conserva las transacciones, el manejo de errores y el comportamiento ante reintentos que ya cubre el flujo.

## React e Inertia

- Usa resources/js/pages para componer pantallas y recibir props de Inertia. Coloca las piezas específicas de Clientes, Laboratorio o Serología junto a los componentes de su módulo y reutiliza los componentes existentes de components/ui para controles compartidos.
- Extrae un componente cuando represente una parte reconocible de la interfaz con una responsabilidad y props claras. Mantén el estado cerca del lugar que lo usa y elévalo cuando varias piezas tengan que coordinarse.
- Extrae un hook cuando el estado, los efectos o las peticiones formen un flujo cohesivo que convenga leer por separado. En filtros o formularios, comprueba carga, errores, cancelación y sincronización con los datos del servidor según corresponda. Conserva el comportamiento de navegación de Inertia.
- Mantén funciones puras, constantes y tipos específicos cerca del módulo que los usa. Lleva a resources/js/lib las utilidades genuinamente compartidas y usa nombres que describan su propósito.

## Verificación

- Para una funcionalidad nueva, cubre el caso principal con una prueba de funcionalidad cuando intervenga Laravel. Comprueba también los casos relevantes de entradas inválidas, falta de permisos o datos de otra empresa. Para cambios de interfaz, verifica que el flujo se pueda completar desde su punto de entrada.
- Usa las pruebas existentes del flujo como referencia antes y después de una refactorización. Añade cobertura focalizada si falta una comprobación para un comportamiento que el cambio podría romper.
- En frontend, verifica la interacción afectada en su pantalla cuando esté disponible. Usa npm run build para comprobar la compilación cuando corresponda. En Laravel, ejecuta las pruebas de PHPUnit del flujo y sigue las indicaciones de Pint de Laravel Boost.
- Al cerrar el cambio, resume el flujo añadido o la responsabilidad que se reorganizó y las comprobaciones ejecutadas. Señala cualquier comportamiento que quede por verificar.
