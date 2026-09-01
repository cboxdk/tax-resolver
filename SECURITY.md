# Security policy

## Reporting a vulnerability

Please do not open a public issue for a security vulnerability.

Report it privately through GitHub's **“Report a vulnerability”** button under this
repository's **Security** tab (Private Vulnerability Reporting). We will acknowledge the
report and keep you updated on the fix.

## Scope

This package is a pure resolver: it takes an already-parsed address or point and a
dataset and returns which taxing authorities apply. It performs no I/O, no network, and
no geocoding. The most relevant concerns are correctness of resolution (a wrong
jurisdiction is a wrong tax) and denial-of-service on malformed input; both are in scope.
