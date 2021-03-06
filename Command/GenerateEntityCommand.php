<?php

namespace Rana\RanaToolsBundle\Command;

use Rana\RanaToolsBundle\Util\CommandUtil;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Question\Question;

class GenerateEntityCommand extends ContainerAwareCommand
{
    private $filePaths;
    private $choiceOptions;
    private $uniqueAttributes;
    private $helper;

    protected function configure()
    {
        $this
            ->setName('rana:generate:entity')
            ->setDescription('Create an entity with assert validations')
            ->addArgument('Bundle:Entity', InputArgument::OPTIONAL, 'Bundle name and entity name')
            ->addOption('option', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //Get arguments and options
        $bundleAndEntity = $input->getArgument('Bundle:Entity') ? $input->getArgument('Bundle:Entity') : 'AppBundle:Post';
        
        //Initialize attributes
        $this->choiceOptions = array();
        $this->uniqueAttributes = array();

        //Get helpers
        $this->helper = $this->getHelper('question');

        //Generate values from options
        list($bundleName, $entityName) = CommandUtil::getBundleAndEntityArray($bundleAndEntity, $output);
        
        //Ask for entity name
        $output->writeln('');
        $output->writeln('This command helps you generate Doctrine2 entities.');
        $output->writeln('');
        $output->writeln('First, you need to give the entity name you want to generate.');
        $output->writeln('You must use the shortcut notation like <comment>AppBundle:Post</comment>.');
        $output->writeln('');
        $bundleAndEntity = $this->helper->ask($input, $output, new Question('<info>Please enter the name of the bundle</info> [<comment>'.$bundleName.':'.$entityName.'</comment>]:', $bundleAndEntity));

        //Regenerate values from options
        list($bundleName, $entityName) = CommandUtil::getBundleAndEntityArray($bundleAndEntity, $output);

        $this->initializeFilePaths($bundleName, $entityName, $output);
        
        //Verify that the entity does not exist
        if (file_exists($this->filePaths['entity'])) {
            $output->writeln('<comment>'.$this->filePaths['entity'].' already exist and it will not be created.</comment>');
        } else {
            $this->createEntity($bundleName, $entityName, $input, $output);
        }

        //Create Verify that the entity does not exist
        if (file_exists($this->filePaths['repository'])) {
            $output->writeln('<comment>'.$this->filePaths['repository'].' already exist and it will not be created.</comment>');
        } else {
            $this->createRepository($bundleName, $entityName, $input, $output);
        }

        $output->writeln('');
        $output->writeln('<question>                                         </question>');
        $output->writeln('<question>  Everything is OK! Now get to work :).  </question>  ');
        $output->writeln('<question>                                         </question>');
        $output->writeln('');
    }

    private function initializeFilePaths($bundleName, $entityName, $output)
    {
        $this->filePaths = array(
            'entity' => 'src/'.$bundleName.'/Entity/'.$entityName.'.php',
            'entityBaseTemplate' => __dir__ . '/Templates/entityBaseTemplate.txt',
            'repository' => 'src/'.$bundleName.'/Repository/'.$entityName.'Repository.php',
            'repositoryTemplate' => __dir__ . '/Templates/repositoryTemplate.txt'
        );
    }

    private function createEntity($bundleName, $entityName, $input, $output)
    {
        //Read entity base template
        $newEntity = CommandUtil::readAndCloseFile($this->filePaths['entityBaseTemplate']);

        //Report configuration format
        $output->writeln('');
        $output->writeln('Using <comment>Annotation</comment> as configuration format.');

        //Ask for serializer group
        $output->writeln('');
        $entityNameUnderscore = CommandUtil::toUnderscore($entityName);
        $group = $this->helper->ask($input, $output, new Question('<info>Group (write "false" to avoid add a group)</info> [<comment>'.$entityNameUnderscore.'</comment>]:', $entityNameUnderscore));
        if ($group === 'false') {
            $group = null; 
        }

        //Add attributes
        $output->writeln('');
        $output->writeln('Instead of starting with a blank entity, you can add some fields now.');
        $output->writeln('Note that the primary key will be added automatically (named id).');
        $output->writeln('');
        $fieldName = $this->helper->ask($input, $output, new Question('<info>New field name (press <return> to stop adding fields): </info>', ''));

        while ($fieldName) {
            $output->writeln('');
            $output->writeln('<info>Available types:</info> <comment>boolean, choice, integer, string, text, datetime, float.</comment>');
            $output->writeln('');

            $fieldType = $this->helper->ask($input, $output, new Question('<info>Field type </info> [<comment>string</comment>]:', 'string'));
            $fieldLength = null;
            $onlyPositive = false;
            $isNullable = 'true';
            $unique = 'false';
            $wrongType = false;
            switch ($fieldType) {
                case 'boolean':
                    break;
                case 'choice':
                    $isNullable = $this->askForIsNullable($input, $output);
                    $fieldLength = $this->helper->ask($input, $output, new Question('<info>Field length </info> [<comment>255</comment>]:', 255));
                    $unique = $this->askForIsUnique($input, $output);
                    break;
                case 'string':
                    $isNullable = $this->askForIsNullable($input, $output);
                    $fieldLength = $this->helper->ask($input, $output, new Question('<info>Field length </info> [<comment>255</comment>]:', 255));
                    $unique = $this->askForIsUnique($input, $output);
                    break;
                case 'text':
                    $isNullable = $this->askForIsNullable($input, $output);
                    break;
                case 'datetime':
                    $isNullable = $this->askForIsNullable($input, $output);
                    $unique = $this->askForIsUnique($input, $output);
                    break;
                case 'integer':
                case 'float':
                    $isNullable = $this->askForIsNullable($input, $output);
                    $onlyPositive = $this->helper->ask($input, $output, new Question('<info>Only positive </info> [<comment>false</comment>]:', false));
                    $onlyPositive = $onlyPositive === 'true';
                    $unique = $this->askForIsUnique($input, $output);
                    break;
                default:
                    $wrongType = true;
                    $output->writeln('<error>Invalid type "'.$fieldType.'"</error>');
                    $output->writeln('<info>Available types:</info> <comment>array, boolean, choice, integer, string, text, datetime, float.</comment>');
                    break;
            }
            if (!$wrongType) {
                $this->addColumnInfo($newEntity, $fieldName, $fieldType, $fieldLength, $isNullable, $unique);
                $this->addAssertInfo($newEntity, $fieldName, $fieldType, $fieldLength, $isNullable, $onlyPositive);
                $this->addGroupInfo($newEntity, $group);
                $this->addFieldInfo($newEntity, $fieldName);
                $this->registerUniqueField($fieldName, $unique);
                $this->registerChoiceOptions($fieldName, $fieldType, $isNullable);
            }

            $output->writeln('');
            $fieldName = $this->helper->ask($input, $output, new Question('<info>New field name (press <return> to stop adding fields): </info>', ''));
        }

        $this->addUniqueEntityValidations($newEntity);
        $this->addAssociations($newEntity);
        $this->addStaticMethods($newEntity);
        $this->addEventsMethods($newEntity);
        $this->addGettersAndSetters($newEntity);
        $this->retouchEntityClass($newEntity, $bundleName, $entityName);

        //Write file
        $output->writeln('');
        $output->writeln('<question>                     </question>');
        $output->writeln('<question>  Entity generation  </question>');
        $output->writeln('<question>                     </question>');
        $output->writeln('');

        CommandUtil::writeAndCloseFile($this->filePaths['entity'], $newEntity);

        $output->writeln('Generating entity class <info>'.$this->filePaths['entity'].'</info>: <comment>OK!</comment>');
    }

    private function createRepository($bundleName, $entityName, $input, $output)
    {
        //Read repository template
        $newRepository = CommandUtil::readAndCloseFile($this->filePaths['repositoryTemplate']);
    
        $newRepository = str_replace('@@Bundle@@', $bundleName, $newRepository);
        $newRepository = str_replace('@@Entity@@', $entityName, $newRepository);

        //Write file
        $output->writeln('');
        $output->writeln('<question>                         </question>');
        $output->writeln('<question>  Repository generation  </question>');
        $output->writeln('<question>                         </question>');
        $output->writeln('');

        CommandUtil::writeAndCloseFile($this->filePaths['repository'], $newRepository);

        $output->writeln('Generating repository class <info>'.$this->filePaths['repository'].'</info>: <comment>OK!</comment>');
    }

    private function askForIsNullable($input, $output)
    {
        $isNullable = $this->helper->ask($input, $output, new Question('<info>Is nullable </info> [<comment>true</comment>]:', 'true'));
        while($isNullable !== 'true' && $isNullable !== 'false') {
            $output->writeln('<error>Invalid option. Enter true or false</error>');
            $isNullable = $this->helper->ask($input, $output, new Question('<info>Is nullable </info> [<comment>true</comment>]:', 'true'));
        }
        return $isNullable;
    }

    private function askForIsUnique($input, $output)
    {
        $unique = $this->helper->ask($input, $output, new Question('<info>Unique </info> [<comment>false</comment>]:', 'false'));
        while($unique !== 'true' && $unique !== 'false') {
            $output->writeln('<error>Invalid option. Enter true or false"</error>');
            $unique = $this->helper->ask($input, $output, new Question('<info>Unique </info> [<comment>false</comment>]:', 'false'));
        }
        return $unique;
    }

    private function addColumnInfo(&$newEntity, $fieldName, $fieldType, $fieldLength, $isNullable, $unique)
    {
        $fieldLength = ($fieldType === 'string' || $fieldType === 'choice') ? ', length='.$fieldLength : '';
        $isNullable = $isNullable === 'true' ? ', nullable=true' : '';
        $unique = $unique === 'true' ? ', unique=true' : '';

        $varType = $this->generateVarType($fieldType);
        $columnType = $this->generateColumnType($fieldType);

        $newEntity = $newEntity .
            ''."\n".
            '    /**'."\n".
            '     * @var '.$varType."\n".
            '     *'."\n".
            '     * @ORM\Column(name="'.$fieldName.'", type="'.$columnType.'"'.$fieldLength.$isNullable.$unique.')'."\n".
            '     *'."\n";
    }

    private function addAssertInfo(&$newEntity, $fieldName, $fieldType, $fieldLength, $isNullable, $onlyPositive)
    {
        if ($isNullable === 'false') {
            $newEntity = $newEntity .
                '     * @Assert\NotNull()'."\n";
        }

        switch ($fieldType) {
            case 'boolean':
                $newEntity = $newEntity .
                    '     * @Assert\Type("bool")'."\n";
                break;
            case 'choice':
                $newEntity = $newEntity .
                    '     * @Assert\Choice(callback = "get'.ucfirst($fieldName).'Options")'."\n";
                break;
            case 'integer':
                $newEntity = $newEntity .
                    '     * @Assert\Type("integer")'."\n";
                break;
            case 'string':
                $newEntity = $newEntity .
                    '     * @Assert\Length(max='.$fieldLength.')'."\n".
                    '     * @Assert\Type("string")'."\n";
                break;
            case 'text':
                $newEntity = $newEntity .
                    '     * @Assert\Type("string")'."\n";
                break;
            case 'datetime':
                $newEntity = $newEntity .
                    '     * @Assert\DateTime()'."\n";
                break;
            case 'float':
                $newEntity = $newEntity .
                    '     * @Assert\Type("float")'."\n";
                break;
        }

        if ($onlyPositive) {
            $newEntity = $newEntity .
                '     * @Assert\GreaterThan(0)'."\n";
        }

    }

    private function addGroupInfo(&$newEntity, $group)
    {
        if ($group) {
            $newEntity = $newEntity .
                '     *'."\n".
                '     * @Groups({"'.$group.'"})'."\n";
        }
    }

    private function addFieldInfo(&$newEntity, $fieldName)
    {
        $newEntity = $newEntity .
            '     */'."\n".
            '    private $'.$fieldName.';'."\n";
    }

    private function registerUniqueField($fieldName, $unique) 
    {
        if ($unique === 'true') {
            $this->uniqueAttributes[] = '"'.$fieldName.'"';
        }
    }

    private function registerChoiceOptions($fieldName, $fieldType, $isNullable) 
    {
        if ($fieldType === 'choice') {
            $this->choiceOptions[] = array($fieldName, $isNullable);
        }
    }

    private function generateVarType($fieldType)
    {
        $varType = $fieldType;
        if ($varType === 'integer') {
            $varType = 'int';
        } else if ($varType === 'datetime') {
            $varType = '\DateTime';
        } else if ($varType === 'choice' || $varType === 'text') {
            $varType = 'string';
        } else if ($varType === 'boolean') {
            $varType = 'bool';
        }

        return $varType;
    }

    private function generateColumnType($fieldType)
    {
        $columnType = $fieldType;
        if ($columnType === 'choice') {
            $columnType = 'string';
        }
        return $columnType;
    }

    private function addUniqueEntityValidations(&$newEntity)
    {
        $useUniqueEntity = '';
        $uniqueEntityInfo = '';
        if (!empty($this->uniqueAttributes)) {
            
            $useUniqueEntity =
                "\n".
                'use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;';
            
            $uniqueEntityInfo =
                "\n".
                ' * @UniqueEntity('."\n".
                ' *     fields={'.implode(', ',$this->uniqueAttributes).'}'."\n".
                ' * )';
        }
        $newEntity = str_replace('@@UseUniqueEntity@@', $useUniqueEntity, $newEntity);
        $newEntity = str_replace('@@UniqueEntity@@', $uniqueEntityInfo, $newEntity);
    }

    private function addAssociations(&$newEntity)
    {
        $newEntity = $newEntity.
                "\n".
                '    //-----------------------------------------------------'."\n".
                '    // Relaciones'."\n".
                '    //-----------------------------------------------------'."\n";
    }

    private function addStaticMethods(&$newEntity)
    {
        if (!empty($this->choiceOptions)) {
            $newEntity = $newEntity.
                "\n".
                '    //-----------------------------------------------------'."\n".
                '    // Métodos estáticos'."\n".
                '    //-----------------------------------------------------'."\n";
        }
        
        foreach($this->choiceOptions as $element ) {
            
            $element[1] = $element[1] === 'true' ? '            "",'."\n" : '';
          
            $newEntity = $newEntity.
                "\n".
                '    public static function get'.ucfirst($element[0]).'Options()'."\n".
                '    {'."\n".
                '        return array('."\n".
                            $element[1].
                '            "option1",'."\n".
                '            "option2",'."\n".
                '            "option3",'."\n".
                '        );'."\n".
                '    }'."\n";
        }
    }

    private function addEventsMethods(&$newEntity)
    {
        $newEntity = $newEntity.
            "\n".
            '    //-----------------------------------------------------'."\n".
            '    // Eventos'."\n".
            '    //-----------------------------------------------------'."\n";
    }

    private function addGettersAndSetters(&$newEntity)
    {
        $newEntity = $newEntity.
            "\n".
            '    //-----------------------------------------------------'."\n".
            '    // Constructor, getters & setters '."\n".
            '    //-----------------------------------------------------'."\n";
    }

    private function retouchEntityClass(&$newEntity, $bundleName, $entityName)
    {
        $newEntity = $newEntity .
            '}'."\n";

        $newEntity = str_replace('@@Bundle@@', $bundleName, $newEntity);
        $newEntity = str_replace('@@Entity@@', $entityName, $newEntity);
        $newEntity = str_replace('@@EntityUnderscore@@', CommandUtil::toUnderscore($entityName), $newEntity);
    }
}