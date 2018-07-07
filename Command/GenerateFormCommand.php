<?php

namespace Rana\RanaToolsBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;

class GenerateFormCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('rana:generate:form')
            ->setDescription('Create a form using doctrine:generate:form command')
            ->addArgument('Bundle:Entity', InputArgument::REQUIRED, 'Bundle name and entity name')
            ->addOption('option', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //Get arguments and options
        $bundleAndEntity = $input->getArgument('Bundle:Entity');
        $bundleAndEntityArray = preg_split('/:+/', $bundleAndEntity);
        if (count($bundleAndEntityArray) != 2) {
            $output->writeln('<error>Incorrect Bundle:Entity argument, expecting something like AcmeBlogBundle:Blog</error>');
            exit();
        }
        $bundleName = ucfirst($bundleAndEntityArray[0]);
        $entityName = ucfirst($bundleAndEntityArray[1]);
        $entityLCFirst = lcfirst($entityName);

        //Check if the form exists
        $forms = scandir('src/'.$bundleName.'/Form');
        $formName = $entityName.'Type.php';
        if (!array_search($formName, $forms)) {
            //if form does not exist create it using doctrine:generate:form command
            $command = $this->getApplication()->find('doctrine:generate:form');
            $arguments = array(
                'command' => 'doctrine:generate:form',
                'entity' => $bundleAndEntity
            );
            $greetInput = new ArrayInput($arguments);
            $returnCode = $command->run($greetInput, $output);
            if ($returnCode !==0) {
                $output->writeln('<error>Error executing doctrine:generate:form command.</error>');
                exit();
            }
            $output->writeln('The file will be updated ...');
        } else {
            $output->writeln('Form File already exist. The file will be updated ...');
        }

        //Read form file
        $formFilePath = 'src/'.$bundleName.'/Form/'.$entityName.'Type.php';
        $formFile = fopen($formFilePath, 'r') or die('Unable to open form file!');
        $form = fread($formFile, filesize($formFilePath));
        fclose($formFile);

        //Verify if form update is required
        $addCsrf_protection = true;
        $addGetNameFunction = true;
        $removeGetBlockPrefixFunction = true;
        if (strpos($form, '\'csrf_protection\' => false,') !== false) {
            $addCsrf_protection = false;
            $output->writeln('<comment>csrf_protection already exists in form file. No changes will be added.</comment>');
        }
        if (strpos($form, 'public function getName()') !== false) {
            $addGetNameFunction = false;
            $output->writeln('<comment>nameFunction already exists in form file. No changes will be added.</comment>');
        }
        if (strpos($form, 'public function getBlockPrefix()') === false) {
            $removeGetBlockPrefixFunction = false;
            $output->writeln('<comment>getBlockPrefix function does not exist in form file. No changes will be added.</comment>');
        }

        //Update Form
        $csrf_protectionAdded = false;
        $getNameFunctionAdded = false;
        $getBlockPrefixFunctionRemoved = false;

        $lines = array();
        foreach (file($formFilePath) as $line) {
            array_push($lines, $line);
            if ('        $resolver->setDefaults(array('.PHP_EOL === $line && $addCsrf_protection === true) {
                array_push($lines, '            \'csrf_protection\' => false,'.PHP_EOL);
                $output->writeln('<info>csrf_protection configuration added to form file.</info>');
                $csrf_protectionAdded = true;
            }
        }
        if ($addGetNameFunction === true) {
            $linesReverse = array_reverse($lines);
            $lines = array();
            foreach ($linesReverse as $line) {
                if ('    }'.PHP_EOL === $line && $addGetNameFunction === true && $getNameFunctionAdded === false) {
                    array_push(
                        $lines,
                        '    }'.PHP_EOL,
                        '        return \''.$entityLCFirst.'\';'.PHP_EOL,
                        '    {'.PHP_EOL,
                        '    public function getName()'.PHP_EOL,
                        '     */'.PHP_EOL,
                        '     * {@inheritdoc}'.PHP_EOL,
                        '    /**'.PHP_EOL,
                        ''.PHP_EOL
                    );
                    $getNameFunctionAdded = true;
                    $output->writeln('<info>GetName function added to form file.</info>');
                }
                array_push($lines, $line);
            }
            $lines = array_reverse($lines);
        }
        if ($removeGetBlockPrefixFunction === true) {
            foreach ($lines as $key => $line) {
                if ($line == '    public function getBlockPrefix()'.PHP_EOL && $removeGetBlockPrefixFunction === true && $getBlockPrefixFunctionRemoved === false) {
                    unset($lines[$key-3]);
                    unset($lines[$key-2]);
                    unset($lines[$key-1]);
                    unset($lines[$key]);
                    unset($lines[$key+1]);
                    unset($lines[$key+2]);
                    unset($lines[$key+3]);
                    unset($lines[$key+4]);
                    $getBlockPrefixFunctionRemoved = true;
                    $output->writeln('<info>GetBlockPrefix function was removed from file.</info>');
                }
            }
        }
        file_put_contents($formFilePath, $lines);
        if ($addCsrf_protection === true && $csrf_protectionAdded === false) {
            $output->writeln('<error>Csrf_protection configuration could not be added. Please add it manually.</error>');
        }
        if ($addGetNameFunction === true && $getNameFunctionAdded === false) {
            $output->writeln('<error>GetName function could not be added. Please add it manually.</error>');
        }
        if ($removeGetBlockPrefixFunction === true && $getBlockPrefixFunctionRemoved === false) {
            $output->writeln('<error>BlockPrefix function could not be removed. Please remove it manually.</error>');
        }
    }
}
