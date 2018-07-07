<?php

namespace Rana\RanaToolsBundle\Command;

use Rana\RanaToolsBundle\Util\CommandUtil;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCrudcontrollerCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('rana:generate:crudcontroller')
            ->setDescription('Create a basic CRUD controller for an entity')
            ->addArgument('Bundle:Entity', InputArgument::REQUIRED, 'Bundle name and entity name')
            ->addOption('option', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //Get arguments and options
        $bundleAndEntity = $input->getArgument('Bundle:Entity');

        if ($input->getOption('option')) {
            // ...
        }

        $bundleAndEntity = preg_split('/:+/', $bundleAndEntity);
        if (count($bundleAndEntity) != 2) {
            $output->writeln('<error>Incorrect Bundle:Entity argument.</error>');
            exit();
        }

        //Get the bundle name
        $bundleName = ucfirst($bundleAndEntity[0]);
        //Check if the bundle exists
        $bundles = scandir('src');
        if (!array_search($bundleName, $bundles)) {
            $output->writeln('<error>Bundle '.$bundleName.' does not exist.</error>');
            exit();
        }

        //Get entity name
        $entityName = ucfirst($bundleAndEntity[1]);
        //Check if the entity exists
        $entities = scandir('src/'.$bundleName.'/Entity');
        if (!array_search($entityName.'.php', $entities)) {
            $output->writeln('<error>Entity '.$entityName.' does not exist.</error>');
            exit();
        }

        //Check if the form exists
        $forms = scandir('src/'.$bundleName.'/Form');
        $formName = $entityName.'Type.php';
        if (!array_search($formName, $forms)) {
            $output->writeln('<error>The file '.$formName.' does not exist. Please create it using generate:form command.</error>');
            exit();
        }

        //Files path definition
        $controllerTemplateFilePath = __dir__ . '/Templates/controllerTemplate.txt';
        $controllerFilePath = 'src/'.$bundleName.'/Controller/'.$entityName.'Controller.php';
        $routingFilePath = 'app/config/routing.yml';
        $routeTemplateFilePath = __dir__ . '/Templates/routeTemplate.txt';

        //Read controller template
        $controllerTemplateFile = fopen($controllerTemplateFilePath, 'r') or die('Unable to open template file!');
        $newController = fread($controllerTemplateFile, filesize($controllerTemplateFilePath));
        fclose($controllerTemplateFile);

        //Create controller
        $entityOnlyUCFirst = ucfirst(strtolower($entityName));
        $entityLCFirst = lcfirst($entityName);
        $sectionName = substr(implode(' ', preg_split('/(?=[A-Z])/', str_replace('Bundle', '', $bundleName))), 1);

        $newController = str_replace('@@Bundle@@', $bundleName, $newController);
        $newController = str_replace('@@Entity@@', $entityName, $newController);
        $newController = str_replace('@@EntityLowerCase@@', strtolower($entityName), $newController);
        $newController = str_replace('@@EntityUnderscore@@', CommandUtil::toUnderscore($entityName), $newController);
        $newController = str_replace('@@EntityOnlyUCFirst@@', $entityOnlyUCFirst, $newController);
        $newController = str_replace('@@EntityLCFirst@@', $entityLCFirst, $newController);
        $newController = str_replace('@@SectionName@@', $sectionName, $newController);

        //Write controller
        $newControllerfile = fopen($controllerFilePath, 'w') or die('Unable to create controller file!');
        fwrite($newControllerfile, $newController);
        fclose($newControllerfile);

        $output->writeln('<info>The controller was created.</info>');

        //Update routing
        $routingfile = fopen($routingFilePath, 'r+') or die('Unable to update routing file!');
        $routing = fread($routingfile, filesize($routingFilePath));
        if (strpos($routing, $entityLCFirst.':')) {
            fclose($routingfile);
            $output->writeln('<comment>The route was not added because it already exists.</comment>');
        } else {

            //Read route template
            $routeTemplateFile = fopen($routeTemplateFilePath, 'r') or die('Unable to open route template file!');
            $newRoute = fread($routeTemplateFile, filesize($routeTemplateFilePath));
            fclose($routeTemplateFile);

            //Create and add route to config file
            $newRoute = str_replace('@@Entity@@', $entityName, $newRoute);
            $newRoute = str_replace('@@Bundle@@', $bundleName, $newRoute);
            $newRoute = str_replace('@@EntityLCFirst@@', $entityLCFirst, $newRoute);
            fwrite($routingfile, $newRoute);
            fclose($routingfile);

            $output->writeln('<info>config/routing.yml updated.<info>');
        }
    }
}
