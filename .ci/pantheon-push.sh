#!/usr/bin/env bash
# Push the current branch (or first argument) to the same branch on Pantheon Git.
#
# Required env:
#   PANTHEON_GIT_SSH_URL — e.g. ssh://codeserver.dev.SITE_ID@codeserver.dev.SITE_ID.drush.in:2222/~/repository.git
# Optional env:
#   PANTHEON_SSH_PRIVATE_KEY — private key material (CI); if unset, uses default ssh agent / ~/.ssh
#   PANTHEON_PUSH_FORCE — set to 1 to use --force-with-lease (use sparingly)
set -euo pipefail

BRANCH="${1:-$(git rev-parse --abbrev-ref HEAD)}"
if [[ "$BRANCH" == "HEAD" ]]; then
  echo "Detached HEAD; pass branch name as first argument." >&2
  exit 1
fi

if [[ -z "${PANTHEON_GIT_SSH_URL:-}" ]]; then
  echo "PANTHEON_GIT_SSH_URL is not set." >&2
  exit 1
fi

if [[ -n "${PANTHEON_SSH_PRIVATE_KEY:-}" ]]; then
  mkdir -p "${HOME}/.ssh"
  KEY_FILE="${HOME}/.ssh/pantheon_ci"
  printf '%s\n' "$PANTHEON_SSH_PRIVATE_KEY" > "$KEY_FILE"
  chmod 600 "$KEY_FILE"
  export GIT_SSH_COMMAND="ssh -i ${KEY_FILE} -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new -p 2222"
fi

git remote add pantheon "$PANTHEON_GIT_SSH_URL" 2>/dev/null \
  || git remote set-url pantheon "$PANTHEON_GIT_SSH_URL"

PUSH_FLAGS=()
if [[ "${PANTHEON_PUSH_FORCE:-}" == "1" ]]; then
  PUSH_FLAGS+=(--force-with-lease)
fi

echo "Pushing refs/heads/${BRANCH} to Pantheon..."
git push pantheon "HEAD:refs/heads/${BRANCH}" "${PUSH_FLAGS[@]}"
