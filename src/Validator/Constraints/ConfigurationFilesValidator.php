<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ConfigurationFilesValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($project, Constraint $constraint): void
    {
        if (!$constraint instanceof ConfigurationFiles) {
            throw new UnexpectedTypeException($constraint, ConfigurationFiles::class);
        }

        $filesystem = new Filesystem();

        $finder = new Finder();
        $finder->files()->in(__DIR__.'/../../Resources/'.getenv('DOCKER_ENVIRONMENT'))->depth(0);

        foreach ($finder as $file) {
            $filename = str_replace(
                'custom-',
                '',
                "{$project->getLocation()}/var/docker/{$file->getFilename()}"
            );

            if (!$filesystem->exists($filename)) {
                $this->context->buildViolation($constraint->message)->addViolation();
            }
        }
    }
}
