<?php

// 1. Force le script à se positionner dans le dossier racine de l'application Oxymaux
chdir(__DIR__);

// 2. Exécute le Scheduler Symfony (limité à 10 secondes max)
shell_exec('/usr/bin/php8.3 bin/console scheduler:consume default --time-limit=10 --no-interaction --env=prod > /dev/null 2>&1');

// 3. Exécute le Worker Messenger pour envoyer les e-mails (limité à 40 secondes max)
shell_exec('/usr/bin/php8.3 bin/console messenger:consume async --time-limit=40 --limit=50 --no-interaction --env=prod > /dev/null 2>&1');
