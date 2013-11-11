<?php

/*
 * (c) Markus Lanthaler <mail@markus-lanthaler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ML\HydraBundle\Command;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Sensio\Bundle\GeneratorBundle\Command\GenerateDoctrineCrudCommand as SensioCrudCommand;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use ML\HydraBundle\Generator\DoctrineCrudGenerator;

/**
 * Generates an API controller supporting CRUD operations for a Doctrine entity.
 *
 * @author Markus Lanthaler <mail@markus-lanthaler.com>
 */
class GenerateDoctrineCrudCommand extends SensioCrudCommand
{
    private $generator;
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputOption('entity', '', InputOption::VALUE_REQUIRED, 'The entity class name to initialize (shortcut notation)'),
                new InputOption('route-prefix', '', InputOption::VALUE_REQUIRED, 'The route prefix'),
                new InputOption('with-write', '', InputOption::VALUE_NONE, 'Whether or not to generate create, new and delete actions'),
                new InputOption('overwrite', '', InputOption::VALUE_NONE, 'Do not stop the generation if crud controller already exist, thus overwriting all generated files')
            ))
            ->setDescription('Generates a Hydra controller for a Doctrine entity supporting CRUD operations')
            ->setHelp(<<<EOT
The <info>hydra:generate:crud</info> command generates an API controller for a Doctrine entity supporting the CRUD operations.

The default command only generates the safe GET operations for the collection and the entity.

<info>php app/console hydra:generate:crud:crud --entity=AcmeBlogBundle:Post --route-prefix=/prefix</info>

Using the --with-write option allows to generate the POST operation for the collection and PUT and DELETE for the entity.

<info>php app/console hydra:generate:crud --entity=AcmeBlogBundle:Post --route-prefix=/prefix --with-write</info>

If you pass  all required options directly from the command line you can turn the interactive mode by passing
the --no-interaction flag.
EOT
            )
            ->setName('hydra:generate:crud')
            ->setAliases(array('generate:doctrine:hydra-crud'))
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dialog = $this->getDialogHelper();

        if ($input->isInteractive()) {
            if (!$dialog->askConfirmation($output, $dialog->getQuestion('Do you confirm generation', 'yes', '?'), true)) {
                $output->writeln('<error>Command aborted</error>');

                return 1;
            }
        }

        $entity = Validators::validateEntityName($input->getOption('entity'));
        list($bundle, $entity) = $this->parseShortcutNotation($entity);

        $format = 'annotation';
        $prefix = $this->getRoutePrefix($input, $entity);
        $withWrite = $input->getOption('with-write');
        $forceOverwrite = $input->getOption('overwrite');

        $dialog->writeSection($output, 'CRUD generation');

        $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($bundle).'\\'.$entity;
        $metadata    = $this->getEntityMetadata($entityClass);
        $bundle      = $this->getContainer()->get('kernel')->getBundle($bundle);

        $generator = $this->getGenerator($bundle);
        $generator->generate($bundle, $entity, $metadata[0], $format, $prefix, $withWrite, $forceOverwrite);

        $output->writeln('Generating the CRUD code: <info>OK</info>');

        $errors = array();
        $runner = $dialog->getRunner($output, $errors);

        $dialog->writeGeneratorSummary($output, $errors);
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $dialog = $this->getDialogHelper();
        $dialog->writeSection($output, 'Welcome to the Hydra CRUD generator');

        // namespace
        $output->writeln(array(
            '',
            'This command helps you generate CRUD controllers for Doctrine entities.',
            '',
            'First, you need to give the entity for which you want to generate the CRUD controller.',
            // 'You can give an entity that does not exist yet and the wizard will help',
            // 'you defining it.',
            '',
            'You must use the shortcut notation like <comment>AcmeBlogBundle:Post</comment>.',
            '',
        ));

        $entityNames = array();
        $srcDir = realpath($this->getContainer()->get('kernel')->getRootDir() . '/../src/') . DIRECTORY_SEPARATOR;

        foreach ($this->getContainer()->get('kernel')->getBundles() as $bundleName => $bundle) {
            $finder = Finder::create();
            $entityDir = $bundle->getPath() . DIRECTORY_SEPARATOR . 'Entity' . DIRECTORY_SEPARATOR;
            if (file_exists($entityDir) && (0 === strncmp($entityDir, $srcDir, strlen($srcDir)))) {
                $finder
                    ->files()
                    ->in($entityDir)
                    ->notName('*Repository')
                    ->getIterator()
                ;

                foreach ($finder as $file) {
                    $entityNames[] = sprintf('%s:%s', $bundleName, str_replace(array($entityDir, '.php'), '', $file->getRealPath()));
                }
            }
        }

        $entity = $dialog->askAndValidate(
            $output,
            $dialog->getQuestion('The Entity shortcut name', $input->getOption('entity')),
            array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateEntityName'),
            false,
            $input->getOption('entity'), $entityNames
        );

        $input->setOption('entity', $entity);
        list($bundle, $entity) = $this->parseShortcutNotation($entity);

        // Entity exists?
        $entityClass = $this->getContainer()->get('doctrine')->getAliasNamespace($bundle).'\\'.$entity;
        $metadata = $this->getEntityMetadata($entityClass);

        // write?
        $withWrite = $input->getOption('with-write') ?: false;
        $output->writeln(array(
            '',
            'By default, the generator only creates the safe GET operations for the collection and the entity.',
            'You can also ask it to generate "write" actions: POST, PUT, and DELETE.',
            '',
        ));
        $withWrite = $dialog->askConfirmation($output, $dialog->getQuestion('Do you want to generate the "write" actions', $withWrite ? 'yes' : 'no', '?'), $withWrite);
        $input->setOption('with-write', $withWrite);

        // route prefix
        $prefix = $this->getRoutePrefix($input, $entity);
        $output->writeln(array(
            '',
            'Determine the routes prefix (all the routes will be "mounted" under this',
            'prefix: /prefix/, /prefix/{id}, ...).',
            '',
        ));
        $prefix = $dialog->ask($output, $dialog->getQuestion('Routes prefix', '/'.$prefix), '/'.$prefix);
        $input->setOption('route-prefix', $prefix);

        // summary
        $output->writeln(array(
            '',
            $this->getHelper('formatter')->formatBlock('Summary before generation', 'bg=blue;fg=white', true),
            '',
            sprintf("You are going to generate a CRUD controller for \"<info>%s:%s</info>\"", $bundle, $entity),
            'using annotations.',
            '',
        ));
    }

    protected function createGenerator($bundle = null)
    {
        return new DoctrineCrudGenerator($this->getContainer()->get('filesystem'));
    }

    protected function getSkeletonDirs(BundleInterface $bundle = null)
    {
        $skeletonDirs = array();

        if (isset($bundle) && is_dir($dir = $bundle->getPath().'/Resources/HydraBundle/skeleton')) {
            $skeletonDirs[] = $dir;
        }

        if (is_dir($dir = $this->getContainer()->get('kernel')->getRootdir().'/Resources/HydraBundle/skeleton')) {
            $skeletonDirs[] = $dir;
        }

        $skeletonDirs[] = __DIR__.'/../Resources/skeleton';
        $skeletonDirs[] = __DIR__.'/../Resources';

        return $skeletonDirs;
    }
}
