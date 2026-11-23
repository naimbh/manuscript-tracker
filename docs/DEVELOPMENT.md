# Development

Notes for working on the Manuscript Tracker code. For installing and configuring the app, see [Installation and setup](INSTALLATION.md).

## Tests

```bash
php artisan test
```

## Front-end build

The built front end (`public/build`) is committed, so the server does not need Node.js. If you change the CSS or JavaScript, run `git config core.hooksPath .githooks` once; the pre-commit hook then rebuilds and commits `public/build` for you.
