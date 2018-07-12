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
    private $choiceOptions;
    private $uniqueAttributes;

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
        $bundleAndEntity = $input->getArgument('Bundle:Entity');
        
        //Initialize attributes
        $this->choiceOptions = array();
        $this->uniqueAttributes = array();

        //Get helpers
        $helper = $this->getHelper('question');

        //Ask for entity name
        $output->writeln('');
        $output->writeln('This command helps you generate Doctrine2 entities.');
        $output->writeln('');
        $output->writeln('First, you need to give the entity name you want to generate.');
        $output->writeln('You must use the shortcut notation like <comment>AcmeBlogBundle:Post</comment>.');
        $output->writeln('');
        $bundleAndEntity = $helper->ask($input, $output, new Question('<info>Please enter the name of the bundle</info> [<comment>AppBundle:Post</comment>]:', 'AppBundle:Post'));

        //Generate values from options
        $bundleAndEntityArray = CommandUtil::getBundleAndEntityArray($bundleAndEntity, $output);
        $bundleName = ucfirst($bundleAndEntityArray[0]);
        $entityName = ucfirst($bundleAndEntityArray[1]);
        $entityLCFirst = lcfirst($entityName);
        $entityNameUnderscore = CommandUtil::toUnderscore($entityName);
        
        //Files path definition
        $entityFilePath = 'src/'.$bundleName.'/Entity/'.$entityName.'.php';
        $entityBaseFilePath = __dir__ . '/Templates/entityBaseTemplate.txt';
        
        //Verify if the entity exists
        if (file_exists($entityFilePath)) {
            $output->writeln('<error>'.$entityFilePath.' already exist.</error>');
            exit();
        }

        //Read entity base template
        $entityBaseFile = fopen($entityBaseFilePath, 'r') or die('Unable to open template file!');
        $newEntity = fread($entityBaseFile, filesize($entityBaseFilePath));
        fclose($entityBaseFile);

        //Report configuration format
        $output->writeln('');
        $output->writeln('Using <comment>Annotation</comment> as configuration format.');
        
        //Ask for serializer group
        $output->writeln('');
        $group = $helper->ask($input, $output, new Question('<info>Group (write "false" to avoid add a group)</info> [<comment>'.$entityNameUnderscore.'</comment>]:', $entityNameUnderscore));
        if ($group === 'false') {
            $group = null; 
        }

        //Add attributes
        $output->writeln('');
        $output->writeln('Instead of starting with a blank entity, you can add some fields now.');
        $output->writeln('Note that the primary key will be added automatically (named id).');
        $output->writeln('');
        $fieldName = $helper->ask($input, $output, new Question('<info>New field name (press <return> to stop adding fields): </info>', ''));
        
        while ($fieldName) {

            $output->writeln('');
            $output->writeln('<info>Available types:</info> <comment>boolean, choice, integer, string, text, datetime, float.</comment>');
            $output->writeln('');
            $fieldType = $helper->ask($input, $output, new Question('<info>Field type </info> [<comment>string</comment>]:', 'string'));
            $fieldLength = null;
            $onlyPositive = false;
            $wrongType = false;
            switch ($fieldType) {
                case 'boolean':
                    break;
                case 'choice':
                    break;
                case 'string':
                    $fieldLength = $helper->ask($input, $output, new Question('<info>Field length </info> [<comment>255</comment>]:', 255));
                    break;
                case 'text':
                    break;
                case 'datetime':
                    break;
                case 'integer':
                case 'float':
                    $onlyPositive = $helper->ask($input, $output, new Question('<info>Only positive </info> [<comment>false</comment>]:', false));
                    $onlyPositive = $onlyPositive === 'true';
                    break;
                default:
                    $wrongType = true;
                    $output->writeln('<error>Invalid type "'.$fieldType.'"</error>');
                    $output->writeln('<info>Available types:</info> <comment>array, boolean, choice, integer, string, text, datetime, float.</comment>');
                    break;
            }
            if (!$wrongType) {
                $isNullable = $helper->ask($input, $output, new Question('<info>Is nullable </info> [<comment>false</comment>]:', false));
                $unique = $helper->ask($input, $output, new Question('<info>Unique </info> [<comment>false</comment>]:', false));                    
                
                $this->addColumnInfo($newEntity, $fieldName, $fieldType, $fieldLength, $isNullable, $unique);
                $this->addAssertInfo($newEntity, $fieldName, $fieldType, $fieldLength, $isNullable, $onlyPositive);
                $this->addGroupInfo($newEntity, $group);
                $this->addFieldInfo($newEntity, $fieldName);
                $this->registerUniqueField($fieldName, $unique);
                $this->registerChoiceOptions($fieldName, $fieldType, $isNullable);
             
                $output->writeln($newEntity);
            }

            $output->writeln('');
            $fieldName = $helper->ask($input, $output, new Question('<info>New field name (press <return> to stop adding fields): </info>', ''));
        }

        $this->addUniqueEntityValidations($newEntity);
        $this->addStaticMethods($newEntity);
        $this->retouchEntityClass($newEntity, $bundleName, $entityName);

        //Write file
        $output->writeln('');
        $output->writeln('<question>                     </question>');
        $output->writeln('<question>  Entity generation  </question>');
        $output->writeln('<question>                     </question>');
        $output->writeln('');

        $newEntityfile = fopen($entityFilePath, 'w') or die('Unable to create entity file!');
        fwrite($newEntityfile, $newEntity);
        fclose($newEntityfile);

        $output->writeln('Generating entity class <info>'.$entityFilePath.'</info>: <comment>OK!</comment>');
        $output->writeln('');
        $output->writeln('<question>                                         </question>');
        $output->writeln('<question>  Everything is OK! Now get to work :).  </question>  ');
        $output->writeln('<question>                                         </question>');
        $output->writeln('');
    }

    private function addColumnInfo(&$newEntity, $fieldName, $fieldType, $fieldLength, $isNullable, $unique)
    {
        $fieldLength = $fieldType === 'string' ? ', length='.$fieldLength : '';
        $isNullable = $isNullable ? ', nullable=true' : '';
        $unique = $unique ? ', unique=true' : '';

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
        if (!$isNullable) {
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
        if ($unique) {
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
            
            $element[1] = $element[1] ? '            "",'."\n" : '';
          
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

    private function retouchEntityClass(&$newEntity, $bundleName, $entityName)
    {
        $newEntity = $newEntity .
            '}'."\n";

        $newEntity = str_replace('@@Bundle@@', $bundleName, $newEntity);
        $newEntity = str_replace('@@Entity@@', $entityName, $newEntity);
        $newEntity = str_replace('@@EntityUnderscore@@', CommandUtil::toUnderscore($entityName), $newEntity);
    }
}
