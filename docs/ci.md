# Continuous integration

`.github/workflows/ci.yaml` runs five jobs. Pull requests run QA and Tests; pushes to `main` and
manual dispatch run all five.

| Job | Runs on | Runs |
| --- | --- | --- |
| `qa` | `ubuntu-latest` with setup-php | actionlint over `.github/workflows/`, `composer audit`, then the composer scripts `lint`, `dependencies`, `rector:check`, `cs:check` and `stan`. The PHP extensions come from the `ext-*` entries of `composer.json`. |
| `tests` | the compose stack | `curl` checks of the app and the Mercure hub, `tailwind:build`, the `test` database and its migrations, `composer test` and `doctrine:schema:validate` inside the `php` service, built from `compose.yaml` and `compose.override.yaml`. |
| `production-image` | buildx | Builds the `frankenphp_prod` target, which deploys use and no other job builds. Starts it beside PostgreSQL with the variables a deploy must set, then checks that `/health-check` and `/login` answer and that the entrypoint ran the migrations. |
| `panther` | the compose stack | The Panther journeys, after Tests ([End-to-end journeys](e2e.md#ci)). |
| `playwright` | the compose stack | The Playwright journeys, after Tests ([End-to-end journeys](e2e.md#ci)). |

The QA gates are composer scripts, so `make qa-check` runs the same ones. Add a new gate to
`composer.json` and call it from the workflow, never to the workflow alone.

The compose jobs install Compose through `docker/setup-compose-action`, pinned to v5, rather than
use the one GitHub's image preinstalls. That version fails `docker compose up --wait` on any service
whose healthcheck is disabled, as `cron`, `worker` and `tailwind` have, with "has no healthcheck
configured"; v5 counts such a service ready once it runs.

## Running the workflow locally

```bash
make ci                 # every job
make ci JOB=tests       # one job
```

Nothing is ready to push until `make ci` passes. It runs `.github/act/run.sh` on the host. The
repository directory name, `ACT_PROJECT`, names everything below, so checkouts in directories of
different names get separate runners.

1. actionlint over `.github/workflows/`.
2. Starts the runner, compose project `<repository>-act` from `.github/act/compose.yaml`: one
   privileged `docker:dind` container with [act](https://github.com/nektos/act) installed. The jobs
   get a Docker daemon of their own, so they publish their ports and use the compose project name
   `<repository>` as they do on GitHub, and the development stack is never touched.
3. For each job, in workflow order:
   1. Copies a snapshot of the working tree into the workspace, a volume at GitHub's path
      `/home/runner/work/<repository>/<repository>`. The snapshot is what `git add --all` would
      commit: untracked files are in it, ignored files are not. The checkout's index and HEAD are
      not touched. The snapshot gets the checkout's `origin` remote when the checkout has one.
   2. Removes every container, volume and network from the runner's daemon. Images stay.
   3. Deletes the `needs:` lines from the snapshot's workflow. act runs a job's needs as part of
      that job, in the same workspace and daemon, which GitHub never does.
   4. Runs act for that job with the event in `.github/act/event.json`, a push to `main`, so the
      conditions of `production-image`, `panther` and `playwright` hold.
4. Prints the jobs that passed and failed, and exits non-zero if any failed.

Each job's output is in `var/ci/logs/<job>.log`.

The act flags are in `.github/act/actrc`, copied into the runner image. `--env-file /dev/null` is
the one that matters most: without it act loads the project's `.env` into every step.

## Where it differs from GitHub

- **Architecture.** The runner has the host's architecture, arm64 on Apple silicon. GitHub's
  `ubuntu-latest` is amd64.
- **Runner image.** `catthehacker/ubuntu:act-24.04` is a subset of GitHub's `ubuntu-24.04`. A step
  that relies on a tool GitHub preinstalls and the subset lacks fails locally only. The compose jobs
  install their own Compose, so it matches; buildx is each image's own.
- **Job order.** Every job runs even when an earlier one failed. GitHub skips `panther` and
  `playwright` after a failed `tests`.
- **Caches.** `actions/cache` and buildx's `gha` cache go to act's cache server, which keeps them in
  the runner's `act` volume between runs. The keys are GitHub's, so a changed `composer.lock` misses
  locally when it misses on GitHub. GitHub also evicts caches unused for 7 days; the runner never
  does.
- **Token.** act has no GitHub token, so the actions call the GitHub API anonymously.
- **Artifacts.** act 0.2.89's artifact server rejects the `mime_type` field current artifact clients
  send ([nektos/act#6114](https://github.com/nektos/act/issues/6114)). The upload steps of `panther`
  and `playwright` are skipped under act (`!env.ACT`), and `DOCKER_BUILD_RECORD_UPLOAD: false` keeps
  the Docker actions from uploading their build records. Review screenshots from `make test-e2e`
  instead ([End-to-end journeys](e2e.md#screenshots)).

## Maintenance

Nothing updates these pins automatically:

| Pin | Where | Keep it at |
| --- | --- | --- |
| `docker:<version>-dind` | `.github/act/Dockerfile` | The `docker-ce` version in GitHub's [ubuntu 24.04 toolset](https://github.com/actions/runner-images/blob/main/images/ubuntu/toolsets/toolset-2404.json). |
| `ACT_VERSION` | `.github/act/Dockerfile` | The latest act release. |
| Compose `version` | `.github/workflows/ci.yaml`, `tests`, `panther` and `playwright` | A current Compose v5 release, the same in all three. |
| `rhysd/actionlint:<version>` | `.github/act/run.sh` and `.github/workflows/ci.yaml` | The same release in both. |
| supercronic release and checksums | `Dockerfile`, `supercronic_amd64` and `supercronic_arm64` | The latest release. Take each `sha256:` from the `digest` of its asset in the GitHub release. |

Dependabot (`.github/dependabot.yml`) opens a pull request when a `FROM` image in `Dockerfile` gets a
new digest or release. The digests keep the build cache valid between those pull requests.

`make ci` rebuilds the runner image on every run, so a changed pin applies to the next run.

A failed job's containers stay in the runner's daemon until the next job starts. `run.sh` exports
`ACT_PROJECT`, which `.github/act/compose.yaml` requires, so set it when you call Compose yourself:

```bash
export ACT_PROJECT=$(basename "$(git rev-parse --show-toplevel)")
docker compose -f .github/act/compose.yaml exec runner docker ps --all
docker compose -f .github/act/compose.yaml exec runner docker logs <container>
```

The runner stays up after a run, so the next one skips the start:

```bash
docker compose -f .github/act/compose.yaml stop            # stops it
docker compose -f .github/act/compose.yaml down --volumes  # removes it with its images, caches and workspace
```

After `down --volumes` the next `make ci` starts cold.
