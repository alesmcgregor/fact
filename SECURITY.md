# Security policy

## Reporting a vulnerability

Please do not disclose suspected vulnerabilities in a public issue or pull request. Use GitHub's **Report a vulnerability** flow on this repository when private vulnerability reporting is available. Otherwise, contact the maintainer through the contact method listed on the GitHub profile and request a private security channel.

Include the affected version or commit, impact, reproduction steps and any suggested mitigation. Do not attach real credentials, production `.env` files, database snapshots, private user content or unredacted logs. Redact secrets before sharing diagnostic output.

## Scope

Reports involving server-side request forgery, secret exposure, unsafe report rendering, authentication or authorization boundaries, data retention, queue processing and dependency vulnerabilities are in scope. Issues requiring access to a user's private account or credentials should be reported without sharing those credentials.

FACT retrieves public content and sends selected text to configured analysis and search providers. Do not submit content you are not authorized to process. Keep provider keys in environment configuration and rotate a key immediately if it may have been exposed.
