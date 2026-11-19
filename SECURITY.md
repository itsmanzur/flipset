# Security policy

Flipset is a free WordPress plugin. Thank you for helping keep it and its users safe.

## Supported versions

Only the latest release on WordPress.org receives security fixes. Update before reporting, if you can.

## Reporting a vulnerability

Please report privately. Do not open a public issue or post it in the support forum.

1. Open **Security → Report a vulnerability** in the GitHub repository: <https://github.com/itsmanzur/flipset/security/advisories/new>
2. Include the Flipset version, WordPress and PHP versions, what you did, what happened, and a small proof of concept if you have one. A PDF that triggers the problem is ideal.

You can expect an acknowledgement within 3 working days. Fixes for confirmed issues are released as a patch version and marked "Security" in the changelog. You will be credited unless you prefer not to be.

## Scope

In scope: the plugin's PHP, JavaScript and block code, and the way it loads and displays PDF files.

Out of scope: issues that need an already compromised administrator account, vulnerabilities only in other plugins or themes, and findings in the bundled PDF.js that are already fixed in a newer upstream release and not reachable through Flipset (please still tell us, so we can upgrade).

## Bundled software

Flipset ships Mozilla PDF.js. The version, the known upstream advisories and how Flipset limits their impact are tracked in [`docs/PDFJS.md`](docs/PDFJS.md).
