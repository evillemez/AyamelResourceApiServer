<?php

namespace Ayamel\FilesystemBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Ayamel\FilesystemBundle\Filesystem\FilesystemManager;
use Ayamel\FilesystemBundle\Filesystem\LocalFilesystem;

/**
 * If the filesystem is local, checks database for local files and removes unreferenced files.
 *
 * @author Evan Villemez
 */
class FilesystemPurgeRefsCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('fs:purge:refs')
            ->setDescription('Remove data references to local files that do not exist.')
            ->setDefinition(array(
                new InputOption('update', null, InputOption::VALUE_NONE, 'Specifying this flag will actually remove references to lost files from the database.  Otherwise, the command will just return counts for lost references.'),
            ));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //check for local filesystem
        $c = $this->getContainer();
        $fs = $c->get('ayamel.api.filesystem');
        if (!($fs instanceof FilesystemManager && $fs->getFilesystem() instanceof LocalFilesystem) && !$fs instanceof LocalFilesystem) {
            throw new \RuntimeException("This command will only work for filesystems managing files accessible by this server.");
        }

        //get array of internal uris in database
        $manager = $c->get('doctrine_mongodb')->getManager();
        $mongo = $manager->getConnection();
        $collection = $mongo->selectCollection($c->getParameter('mongodb_database'), "resources");
        $results = $collection->find(array('content.files.internalUri' => array('$exists'=>true)), array('content.files.internalUri' => 1));

        //check if file actually exists for each uri
        $total = 0;
        $lost = 0;
        $removed = 0;
        foreach ($results as $id => $item) {
            foreach ($item['content']['files'] as $file) {
                $total++;
                if (!file_exists($file['internalUri'])) {
                    $lost++;
                    $output->writeln(sprintf("Cannot find %s", $file['internalUri']));

                    //delete or not?
                    if ($input->getOption('update')) {
                        if ($this->removeFileReference($id, $file['internalUri'])) {
                            $removed++;
                        }
                    }
                }
            }
        }

        $output->writeln(sprintf("Could not find <info>%s</info> of <info>%s</info> local files references on disk.  <info>%s</info> db references removed.", $lost, $total, $removed));

        return;
    }

    protected function removeFileReference($id, $path)
    {
        $manager = $this->getContainer()->get('doctrine_mongodb')->getManager();
        $resource = $manager->getRepository('AyamelResourceBundle:Resource')->find($id);

        $newFiles = [];
        foreach ($resource->content->getFiles() as $file) {
            if ($file->getInternalUri() !== $path) {
                $newFiles[] = $file;
            }
        }
        $resource->content->setFiles($newFiles);
        $manager->flush();

        return true;
    }

}
