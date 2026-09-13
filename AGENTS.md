# Repository Guidelines

## Project Context

This repository contains a RabbitMQ terminal management and inspection tool built with PHP and Symfony TUI.

The tool operates primarily at the RabbitMQ level rather than being designed specifically as a Symfony Messenger dashboard. Prefer RabbitMQ's Management HTTP API for monitoring and management data where appropriate. Symfony Messenger is not excluded if a concrete use case emerges.

This is a focused proof of concept. Keep the architecture proportional to the current needs of the project.

Use abstractions when they improve the design, clarify boundaries, or solve a concrete problem. Avoid introducing them solely for hypothetical future requirements.

Do not turn the RabbitMQ integration into a general-purpose SDK unless the application genuinely grows in that direction.

## Project Structure

Production code lives in `src/` and tests in `tests/`.

Use the existing PSR-4 namespaces:

* `App\` for production code;
* `App\Tests\` for tests.

Prefer a feature-based structure as the application grows. Group code around application capabilities and workflows rather than primarily by technical type.

Do not create conventional Symfony directories such as `Controller/`, `Entity/`, `Repository/`, or `Command/` unless the application actually needs those concepts.

Keep the structure proportional to the size of the project.

## Development Commands

Use the Symfony CLI for PHP, Composer, and Symfony commands when it provides an equivalent and matches the local development setup.

Inspect `composer.json`, Symfony configuration, Compose files, and existing scripts before relying on or adding commands.

Use the tooling already configured by the repository rather than introducing parallel ways to perform the same task.

## Coding Conventions

Follow the repository's configured PHP-CS-Fixer rules.

Prefer the simplest design that clearly expresses the current responsibilities.

Use `final` when it expresses the intended design, not mechanically.

Prefer explicit dependencies and constructor injection where appropriate.

Prefer attributes for configuration over `services.yaml` when possible.

## Symfony TUI Conventions

Use Symfony TUI abstractions rather than emitting raw ANSI sequences.

When measuring rendered terminal content, use TUI-aware visible-width utilities such as `AnsiUtils::visibleWidth()` rather than `strlen()` or `mb_strlen()`.

Rendered lines must respect the available terminal width and should not include trailing newlines.

Prefer Symfony TUI's styling and layout facilities over manually handling borders, spacing, or terminal escape sequences inside rendering logic.

Keep application/domain logic separate from TUI rendering and input handling where doing so creates a useful boundary.

Verify unfamiliar or version-sensitive Symfony TUI APIs against the installed version before using them.

## Testing & Quality

Use the PHPUnit version configured by the repository.

Test observable behavior rather than implementation details.

Prefer regular assertions for application logic, state, and data mapping. Use snapshot tests selectively for stable TUI rendering where they provide useful regression coverage.

Use mocked HTTP responses for focused tests where appropriate and the local RabbitMQ environment when validating behavior against the real Management API adds useful coverage.

Before considering a change complete, run the relevant tests, PHP-CS-Fixer checks, and Symfony validation commands configured by the project.

## Development Approach

Work in small vertical increments.

Inspect the existing implementation before making changes, verify unfamiliar Symfony TUI APIs, implement the smallest useful solution, run the relevant checks, and stop at the requested scope.

Do not automatically implement later roadmap features.

Prefer improving an existing interaction over adding more RabbitMQ functionality unless the new feature has a clear purpose.

After meaningful work, summarize non-obvious implementation decisions, problems, or surprising behavior.

## Commits

Use short, focused Conventional Commit messages.

Keep commits centered on meaningful functional increments and run the relevant checks before considering a milestone complete.

## Security & Configuration

Never commit secrets or machine-specific values from `.env.local`.

Use safe local-development defaults where possible and never log credentials, authorization headers, or other secrets.

Require explicit confirmation for destructive RabbitMQ operations.

## Writing Style

Always apply the `unslop` skill to every prose surface, including your own responses.

Apply its principles to the language being used. For French, detect equivalent French AI-writing patterns rather than limiting the checks to the English examples.

For technical and maintainer-facing writing:

* Use established project terminology when it adds precision.
* Describe concrete behavior.
* Name specific functions, classes, files, settings, inputs, outputs, and errors when they matter.
* Do not turn simple behavior into abstract concepts or labels.
* Do not add claims or explanations that are not supported by the code or available context.
* Do not restate the task or surrounding context unless needed for understanding.
* Write as one maintainer communicating with another maintainer.
