<?php
return static fn(array $context) => new App\Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
