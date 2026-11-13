# thecorps/laravel-cqrs

> [🇬🇧 Read in English](README.md)

Infrastruttura CQRS leggera per applicazioni Laravel, costruita su attributi PHP 8.

Fornisce un `CommandBus` e un `QueryBus` che instradano comandi e query attraverso una pipeline di behavior completamente configurabile — logging, validazione, transazioni database ed esecuzione dell'handler — senza boilerplate nel codice consumer.

---

## Requisiti

| Dipendenza | Versione          |
| ---------- | ----------------- |
| PHP        | `^8.2`            |
| Laravel    | `^11.0`, `^12.0` o `^13.0` |

---

## Installazione

```bash
composer require thecorps/laravel-cqrs
```

L'auto-discovery di Laravel registra automaticamente il `CqrsServiceProvider` e le facade `CommandBus` / `QueryBus`. Nessuna registrazione manuale necessaria.

### Pubblicare il file di configurazione

```bash
php artisan vendor:publish --tag=cqrs-config
```

Questo crea `config/cqrs.php` nella tua applicazione, dove puoi personalizzare la pipeline e le impostazioni di logging.

---

## Come funziona

```
CommandBus::dispatch(new CreateUserCommand(...))
    │
    ▼
LoggingBehavior           → logga "[CQRS] CreateUserCommand dispatched"
    │
    ▼
ValidationBehavior        → risolve #[CommandValidator] e chiama validate()
    │
    ▼
TransactionBehavior       → avvolge tutto il resto in DB::transaction()
    │
    ▼
HandlerExecutionBehavior  → risolve #[CommandHandler] e chiama handle()
    │
    ▼
CreateUserCommandHandler::handle(CreateUserCommand $command)
```

Le query seguono lo stesso pattern, ma saltano `LoggingBehavior` e `TransactionBehavior` per default.

---

## Utilizzo

### 1 — Command

Un command è un DTO immutabile che implementa `CommandInterface` e dichiara il suo handler tramite l'attributo `#[CommandHandler]`. Gli attributi vengono anche ereditati: un command che estende una classe base con `#[CommandHandler]` usa quell'handler, a meno che non ne dichiari uno proprio.

```php
use TheCorps\LaravelCqrs\Contracts\Interfaces\Commands\CommandInterface;
use TheCorps\LaravelCqrs\Contracts\Attributes\Commands\CommandHandler;

#[CommandHandler(CreateUserCommandHandler::class)]
final class CreateUserCommand implements CommandInterface
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
    ) {}
}
```

### 2 — Command Handler

```php
use TheCorps\LaravelCqrs\Contracts\Interfaces\Commands\CommandHandlerInterface;

class CreateUserCommandHandler implements CommandHandlerInterface
{
    public function handle(CreateUserCommand $command): User
    {
        return User::create([
            'name'  => $command->name,
            'email' => $command->email,
        ]);
    }
}
```

### 3 — Dispatch

```php
use TheCorps\LaravelCqrs\Facades\CommandBus;

$user = CommandBus::dispatch(new CreateUserCommand(
    name:  $request->validated('name'),
    email: $request->validated('email'),
));
```

Le facade sono facoltative. Per iniettare il bus, indica come tipo `CommandBusInterface` (o `QueryBusInterface`): il container lo risolve nello stesso singleton usato dalla facade, e `CommandBus::fake()` sostituisce entrambi.

```php
use TheCorps\LaravelCqrs\Contracts\Interfaces\Bus\CommandBusInterface;

final class RegistrationController
{
    public function __construct(private readonly CommandBusInterface $commands) {}

    public function store(RegisterRequest $request): RedirectResponse
    {
        $this->commands->dispatch(new CreateUserCommand(...));

        return redirect('/');
    }
}
```

---

### 4 — Query

```php
use TheCorps\LaravelCqrs\Contracts\Interfaces\Queries\QueryInterface;
use TheCorps\LaravelCqrs\Contracts\Attributes\Queries\QueryHandler;

#[QueryHandler(GetUserQueryHandler::class)]
final class GetUserQuery implements QueryInterface
{
    public function __construct(
        public readonly int $userId,
    ) {}
}
```

```php
use TheCorps\LaravelCqrs\Contracts\Interfaces\Queries\QueryHandlerInterface;

class GetUserQueryHandler implements QueryHandlerInterface
{
    public function handle(GetUserQuery $query): ?User
    {
        return User::find($query->userId);
    }
}
```

```php
use TheCorps\LaravelCqrs\Facades\QueryBus;

$user = QueryBus::dispatch(new GetUserQuery(userId: $id));
```

---

### 5 — Validator (opzionale)

I validator contengono regole di dominio che vanno oltre la validazione HTTP dell'input. Sono opt-in: senza `#[CommandValidator]`, il `ValidationBehavior` è un no-op.

```php
#[CommandHandler(CreateUserCommandHandler::class)]
#[CommandValidator(CreateUserCommandValidator::class)]
final class CreateUserCommand implements CommandInterface { ... }
```

```php
use TheCorps\LaravelCqrs\Contracts\Interfaces\Commands\ValidatesCommandInterface;

class CreateUserCommandValidator implements ValidatesCommandInterface
{
    public function validate(object $command): void
    {
        if (User::where('email', $command->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Questa email è già registrata.',
            ]);
        }
    }
}
```

Nella pipeline di default dei command il validator viene eseguito **prima** di `TransactionBehavior`: una validazione fallita interrompe il command senza aprire una transazione sul database.

> **Nota:** un controllo come quello sopra non è atomico. Due richieste concorrenti possono superarlo entrambe prima che una delle due inserisca, anche dentro una transazione. La vera garanzia è un indice unico sulla colonna; il validator serve a restituire un messaggio d'errore chiaro.

---

### 6 — Transazioni

`TransactionBehavior` è incluso nella pipeline di default per i command. Non è necessaria alcuna configurazione: ogni command viene avvolto automaticamente in un `DB::transaction()`. Se l'handler, o un behavior posto dopo `TransactionBehavior`, lancia un'eccezione, l'intera transazione viene annullata. I behavior posti prima, come `ValidationBehavior` nell'ordine di default, vengono eseguiti fuori dalla transazione.

```php
class TransferFundsCommandHandler implements CommandHandlerInterface
{
    public function handle(TransferFundsCommand $command): void
    {
        // Entrambe le scritture avvengono nella stessa transazione DB.
        // Se la seconda lancia un'eccezione, la prima viene rollbackata automaticamente.
        Account::find($command->fromId)->decrement('balance', $command->amount);
        Account::find($command->toId)->increment('balance', $command->amount);
    }
}
```

Per disabilitare le transazioni automatiche, rimuovi `TransactionBehavior` dalla `command_pipeline` nel tuo `config/cqrs.php` pubblicato:

```php
'command_pipeline' => [
    LoggingBehavior::class,
    ValidationBehavior::class,
    // TransactionBehavior::class — rimosso
    HandlerExecutionBehavior::class,
],
```

Per cambiare la transazione di un singolo command, usa un attributo sul command:

```php
use TheCorps\LaravelCqrs\Contracts\Attributes\Transactions\Transactional;
use TheCorps\LaravelCqrs\Contracts\Attributes\Transactions\WithoutTransaction;

#[CommandHandler(SendNewsletterHandler::class)]
#[WithoutTransaction]                       // nessun lavoro sul database, oppure l'handler gestisce le proprie transazioni
final class SendNewsletterCommand implements CommandInterface { ... }

#[CommandHandler(SettleLedgerHandler::class)]
#[Transactional(connection: 'ledger', attempts: 3)]   // altra connessione, fino a 3 tentativi in caso di deadlock
final class SettleLedgerCommand implements CommandInterface { ... }
```

Le opzioni non indicate usano `cqrs.transactions` (`connection` è la connessione di default, `attempts` vale `1`). Entrambi gli attributi vengono ereditati dalle sottoclassi.

> **Nota:** Non aggiungere mai `TransactionBehavior` alla query pipeline. Le query read-only non necessitano di transazioni.

---

### 7 — Logging

`LoggingBehavior` è incluso nella pipeline di default per i command. Scrive una riga quando un command viene inviato e una quando termina, oppure una riga `failed` se viene lanciata un'eccezione. L'eccezione viene poi rilanciata invariata, quindi il gestore delle eccezioni di Laravel continua a registrarla.

```
[CQRS] CreateUserCommand dispatched
[CQRS] CreateUserCommand completed
[CQRS] CreateUserCommand failed
```

Ogni riga porta nel contesto il nome completo della classe (`class`). Le righe `completed` e `failed` portano anche `duration_ms`, e la riga `failed` porta la classe dell'eccezione (`exception`) e il messaggio (`error`).

Di default usa il canale di log predefinito dell'applicazione, a livello `debug` per invio e completamento e a livello `warning` per i fallimenti. Tutte e tre le impostazioni sono configurabili:

```php
// config/cqrs.php
'logging' => [
    'channel'       => 'daily',  // scrivi su un canale di log specifico di Laravel
    'level'         => 'info',   // livello delle righe "dispatched" e "completed"
    'failure_level' => 'error',  // livello della riga "failed"
],
```

Per disabilitare il logging completamente, rimuovi `LoggingBehavior` dalla `command_pipeline`:

```php
'command_pipeline' => [
    // LoggingBehavior::class — rimosso
    ValidationBehavior::class,
    TransactionBehavior::class,
    HandlerExecutionBehavior::class,
],
```

---

### 8 — Risultati tipizzati

`dispatch()` restituisce ciò che restituisce l'handler. Dichiara quel tipo sul command o sulla query e PHPStan, Psalm e gli IDE lo deducono a ogni chiamata, tramite facade, interfaccia del bus o fake:

```php
/** @implements QueryInterface<User|null> */
#[QueryHandler(GetUserQueryHandler::class)]
final class GetUserQuery implements QueryInterface { ... }

$user = QueryBus::dispatch(new GetUserQuery(userId: $id)); // User|null
```

Il parametro di tipo vale `mixed` di default, quindi command e query senza annotazione continuano a funzionare e non generano nuovi errori di analisi statica.

---

### 9 — Convenzioni sui nomi

Un command o una query senza attributo handler viene gestito dalla classe che porta il suo nome nello stesso namespace:

| Messaggio | Handler | Validator |
| --- | --- | --- |
| `CreateUserCommand` | `CreateUserCommandHandler` o `CreateUserHandler` | `CreateUserCommandValidator` o `CreateUserValidator` |
| `GetUserQuery` | `GetUserQueryHandler` o `GetUserHandler` | `GetUserQueryValidator` o `GetUserValidator` |

Una classe trovata così viene usata solo se implementa l'interfaccia corrispondente (`CommandHandlerInterface`, `ValidatesCommandInterface` e gli equivalenti per le query). Gli attributi hanno sempre la precedenza, e i validator vengono cercati per nome solo per i messaggi che non dichiarano un attributo handler: i command esistenti non iniziano mai a eseguire un validator non dichiarato. Imposta `'conventions' => false` in `config/cqrs.php` per rendere gli attributi obbligatori.

---

### 10 — Eventi

Entrambi i bus lanciano eventi Laravel attorno a ogni dispatch:

| Command | Query | Proprietà |
| --- | --- | --- |
| `CommandDispatching` | `QueryDispatching` | `message` |
| `CommandHandled` | `QueryHandled` | `message`, `result`, `durationMs` |
| `CommandFailed` | `QueryFailed` | `message`, `exception`, `durationMs` |

Si trovano in `TheCorps\LaravelCqrs\Events` e sono il punto giusto per metriche, audit o recorder di Telescope/Pulse:

```php
use Illuminate\Support\Facades\Event;
use TheCorps\LaravelCqrs\Events\CommandHandled;

Event::listen(function (CommandHandled $event) {
    logger()->info('Command handled', [
        'command' => $event->message::class,
        'duration_ms' => $event->durationMs,
    ]);
});
```

Gli eventi senza listener non vengono nemmeno creati, quindi costano circa un microsecondo per dispatch. Imposta `'events' => false` per disattivarli. `CommandFailed` viene lanciato prima che l'eccezione sia rilanciata.

---

## Test

Sostituisci un bus con un fake per verificare cosa invia il tuo codice senza eseguire gli handler:

```php
use TheCorps\LaravelCqrs\Facades\CommandBus;
use TheCorps\LaravelCqrs\Facades\QueryBus;

CommandBus::fake();

$this->post('/users', [...]);

CommandBus::assertDispatched(CreateUserCommand::class);
CommandBus::assertDispatched(fn (CreateUserCommand $command) => $command->email === 'ada@example.com');
CommandBus::assertDispatched(CreateUserCommand::class, 1);       // esattamente una volta
CommandBus::assertNotDispatched(DeleteUserCommand::class);
CommandBus::assertNothingDispatched();
```

Con il fake, `dispatch()` restituisce `null` oppure la risposta configurata per classe; una closure riceve il messaggio:

```php
QueryBus::fake([
    GetUserQuery::class => fn (GetUserQuery $query) => User::factory()->make(['id' => $query->userId]),
]);

CommandBus::fake()->respondWith(CreateUserCommand::class, 42);
```

Il fake sostituisce anche il bus iniettato tramite `CommandBusInterface` / `QueryBusInterface` o le classi concrete. `dispatched()` e `hasDispatched()` restituiscono i messaggi registrati per asserzioni personalizzate.

Per testare un handler, invia il messaggio con il bus reale, così validazione e transazione vengono eseguite come in produzione.

---

## Comandi Artisan

| Comando | Scopo |
| --- | --- |
| `make:cqrs-command Billing/CreateInvoice [--validator] [--force]` | Crea il command e il suo handler (e validator) in `app/Domain/Billing/Commands/CreateInvoice/` |
| `make:cqrs-query Billing/GetInvoice [--validator] [--force]` | Crea la query e il suo handler (e validator) in `app/Domain/Billing/Queries/GetInvoice/` |
| `cqrs:list [--path=app]` | Elenca ogni command e query con handler e validator; esce con errore se uno non si risolve, quindi è utile in CI |
| `cqrs:install-agent-rules` | Installa le regole per i coding agent (vedi sotto) |

Le classi generate si chiamano `{UseCase}Command`, `{UseCase}Query`, `{UseCase}Handler` e `{UseCase}Validator`. Il namespace radice si cambia con `cqrs.generators.namespace` (default `Domain`, sotto il namespace dell'applicazione); il codice generato si personalizza con:

```bash
php artisan vendor:publish --tag=cqrs-stubs
```

---

## Configurazione

Dopo la pubblicazione, `config/cqrs.php` espone le pipeline e le opzioni descritte sotto.

### Composizione della pipeline

```php
'command_pipeline' => [
    LoggingBehavior::class,
    ValidationBehavior::class,
    TransactionBehavior::class,
    HandlerExecutionBehavior::class,  // deve essere sempre l'ultimo
],

'query_pipeline' => [
    ValidationBehavior::class,
    HandlerExecutionBehavior::class,  // deve essere sempre l'ultimo
],
```

Puoi aggiungere, rimuovere o riordinare i behavior liberamente. L'ordine nell'array è l'ordine di esecuzione.

Se un command o una query arriva in fondo alla pipeline senza essere gestito, ad esempio perché `HandlerExecutionBehavior` è stato rimosso, il bus lancia una `InvalidPipelineException` invece di restituire `null` in silenzio.

### Behavior personalizzati

Qualsiasi classe che implementa `PipelineBehaviorInterface` può essere inserita nella pipeline. I behavior vengono risolti dal container di Laravel la prima volta che un bus viene usato, quindi supportano l'iniezione nel costruttore e possono dipendere da binding registrati da qualsiasi tuo service provider.

```php
use Closure;
use TheCorps\LaravelCqrs\Contracts\Interfaces\Pipeline\PipelineBehaviorInterface;

class RateLimitingBehavior implements PipelineBehaviorInterface
{
    public function __construct(private readonly RateLimiter $limiter) {}

    public function handle(object $command, Closure $next): mixed
    {
        // la tua logica trasversale qui
        return $next($command);
    }
}
```

```php
// config/cqrs.php
'command_pipeline' => [
    LoggingBehavior::class,
    RateLimitingBehavior::class,   // ← inserito
    ValidationBehavior::class,
    TransactionBehavior::class,
    HandlerExecutionBehavior::class,
],
```

Un behavior il cui costruttore chiede un `MetadataResolver` riceve il resolver della propria pipeline, così può leggere l'handler o il validator del messaggio che sta elaborando. Per lo stesso motivo i behavior integrati si possono estendere:

```php
use Closure;
use TheCorps\LaravelCqrs\Pipeline\Behaviors\ValidationBehavior;

class AuditedValidationBehavior extends ValidationBehavior
{
    public function handle(object $command, Closure $next): mixed
    {
        // la tua logica aggiuntiva qui
        return parent::handle($command, $next);
    }
}
```

La pipeline viene costruita una sola volta per istanza del bus e poi riutilizzata. Le modifiche a `cqrs.command_pipeline` o `cqrs.query_pipeline` fatte a runtime valgono solo prima che il bus venga risolto la prima volta.

### Logging

```php
'logging' => [
    'channel'       => null,       // null = canale di log default di Laravel; oppure 'daily', 'slack', ecc.
    'level'         => 'debug',    // livello PSR-3 delle righe "dispatched" e "completed"
    'failure_level' => 'warning',  // livello PSR-3 della riga "failed"
],
```

### Altre opzioni

```php
'transactions' => [
    'connection' => null,  // connessione di default di TransactionBehavior; null = database.default
    'attempts'   => 1,     // quante volte viene eseguito un command in caso di deadlock
],

'events' => true,          // lancia CommandDispatching/Handled/Failed e gli equivalenti per le query

'conventions' => true,     // trova handler e validator per nome quando manca l'attributo

'generators' => [
    'namespace' => 'Domain',  // make:cqrs-* crea le classi sotto App\Domain
],
```

---

## Errori

Le configurazioni sbagliate falliscono con un'eccezione dedicata che indica la classe coinvolta. Tutte estendono `TheCorps\LaravelCqrs\Exceptions\CqrsException`, che estende `RuntimeException`.

| Eccezione | Quando viene lanciata |
| --- | --- |
| `MissingHandlerException` | il command o la query, e le sue classi padre, non dichiarano `#[CommandHandler]` / `#[QueryHandler]` e nessun handler viene trovato per convenzione |
| `InvalidHandlerException` | la classe handler dichiarata non esiste o non ha un metodo `handle()` |
| `InvalidValidatorException` | la classe validator dichiarata non esiste o non ha un metodo `validate()` |
| `InvalidPipelineException` | una voce della pipeline non è una classe `PipelineBehaviorInterface`, la configurazione della pipeline non è una lista, oppure un messaggio arriva in fondo alla pipeline senza essere gestito |

---

## Struttura del package

```
src/
├── Contracts/
│   ├── Attributes/
│   │   ├── Commands/
│   │   │   ├── CommandHandler.php     ← #[CommandHandler(HandlerClass::class)]
│   │   │   └── CommandValidator.php   ← #[CommandValidator(ValidatorClass::class)]
│   │   ├── Queries/
│   │   │   ├── QueryHandler.php       ← #[QueryHandler(HandlerClass::class)]
│   │   │   └── QueryValidator.php     ← #[QueryValidator(ValidatorClass::class)]
│   │   └── Transactions/
│   │       ├── Transactional.php      ← #[Transactional(connection, attempts)]
│   │       └── WithoutTransaction.php ← #[WithoutTransaction]
│   └── Interfaces/
│       ├── Bus/
│       │   ├── CommandBusInterface.php        ← tipo da usare per iniettare il command bus
│       │   └── QueryBusInterface.php          ← tipo da usare per iniettare il query bus
│       ├── Commands/
│       │   ├── CommandInterface.php           ← implementa su ogni Command DTO
│       │   ├── CommandHandlerInterface.php    ← implementa su ogni CommandHandler
│       │   └── ValidatesCommandInterface.php  ← implementa su ogni CommandValidator
│       ├── Queries/
│       │   ├── QueryInterface.php             ← implementa su ogni Query DTO
│       │   ├── QueryHandlerInterface.php      ← implementa su ogni QueryHandler
│       │   └── ValidatesQueryInterface.php    ← implementa su ogni QueryValidator
│       └── Pipeline/
│           └── PipelineBehaviorInterface.php  ← implementa per behavior personalizzati
├── Console/                              ← make:cqrs-command, make:cqrs-query, cqrs:list, cqrs:install-agent-rules
├── Events/                               ← Command/Query Dispatching, Handled, Failed
├── Exceptions/                           ← CqrsException e sottoclassi
├── Pipeline/
│   ├── Behaviors/
│   │   ├── LoggingBehavior.php           ← logga dispatch, completamento e fallimento
│   │   ├── ValidationBehavior.php        ← esegue il validator se dichiarato
│   │   ├── TransactionBehavior.php       ← avvolge in DB::transaction()
│   │   └── HandlerExecutionBehavior.php  ← risolve e chiama l'handler
│   ├── MetadataResolver.php  ← legge attributi PHP via reflection (cache statica)
│   └── Pipeline.php          ← compone ed esegue la catena di behavior
├── Bus/
│   ├── Bus.php         ← logica di dispatch comune ed eventi
│   ├── CommandBus.php  ← bus concreto registrato nel container
│   └── QueryBus.php
├── Facades/
│   ├── CommandBus.php  ← facade Laravel, con fake()
│   └── QueryBus.php
├── Support/                              ← lettore di attributi, ricerca classi, installer delle regole per agenti
├── Testing/
│   ├── CommandBusFake.php  ← installato da CommandBus::fake()
│   └── QueryBusFake.php    ← installato da QueryBus::fake()
└── CqrsServiceProvider.php  ← registra i bus, unisce config, pubblica file e stub
```

---

## Best practice

**Mantieni Command e Query come DTO immutabili.**
Usa proprietà `readonly` nel costruttore. Un command deve portare esattamente ciò di cui ha bisogno — nessun campo nullable opzionale che maschera dati mancanti.

**Separa la validazione HTTP dalla validazione di dominio.**
Usa `FormRequest` di Laravel per controllare formato, presenza e tipo dei campi. Usa `CommandValidator` per le regole di dominio: unicità, transizioni di stato, vincoli cross-aggregate.

```
FormRequest       → l'email è in formato valido? il campo è presente?
CommandValidator  → questa email è già registrata? questa subscription può essere messa in pausa?
```

**Gli handler devono essere orchestratori snelli.**
Mantieni la business logic in model, domain service o value object. L'unico compito dell'handler è coordinarli.

**Le query non devono mai scrivere.**
Se ti trovi a chiamare `save()` dentro un query handler, sposta quella logica in un command.

**`HandlerExecutionBehavior` deve essere sempre l'ultimo.**
Qualsiasi behavior inserito dopo di esso non verrà mai eseguito, e una pipeline senza di esso lancia una `InvalidPipelineException`.

**Non aggiungere `TransactionBehavior` alla query pipeline.**
Avvolgere query read-only in transazioni è overhead inutile. La configurazione di default già riflette questo.

---

## Integrazione con coding agent

Il package fornisce le regole di cui un coding agent ha bisogno per scrivere codice CQRS coerente con la libreria: una skill `laravel-cqrs` e una breve guideline. Si installano in uno dei due modi.

**Con [Laravel Boost](https://laravel.com/docs/boost)**: esegui `php artisan boost:install` (oppure `boost:update`). Boost riconosce il package e installa guideline e skill per ogni agente selezionato: Claude Code, Codex, Cursor, GitHub Copilot, Junie e altri.

**Senza Boost**: esegui il comando del package.

```bash
php artisan cqrs:install-agent-rules
```

Crea o aggiorna quattro file nella root dell'applicazione:

```text
AGENTS.md                              letto da Codex, Cursor, GitHub Copilot e dalla maggior parte degli altri agenti
CLAUDE.md                              letto da Claude Code
.agents/skills/laravel-cqrs/SKILL.md
.claude/skills/laravel-cqrs/SKILL.md
```

`AGENTS.md` e `CLAUDE.md` ricevono lo stesso blocco delimitato da marker, che rimanda alla skill; il contenuto fuori dai marker non viene toccato. I file collegati da symlink vengono scritti una sola volta. Riesegui il comando dopo aver aggiornato il package per aggiornare le regole. Il comando rifiuta di sovrascrivere uno `SKILL.md` non scritto dal package.

Le regole guidano gli agenti, ma non sostituiscono code review o test architetturali. Consulta la [guida all'integrazione agent](docs/agent-integration.md) per i dettagli.

---

## Sviluppo

```bash
composer install
composer test      # PHPUnit
composer analyse   # PHPStan, livello max
```

La suite di test gira su GitHub Actions per ogni combinazione supportata di PHP e Laravel.

---

## Licenza

Questo package è software open source rilasciato sotto la [MIT License](LICENSE).

Sei libero di usarlo, modificarlo e distribuirlo in progetti privati e commerciali. L'unico requisito è mantenere l'avviso di copyright in qualsiasi copia o parte sostanziale del software.
