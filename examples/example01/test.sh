echo '###' Start
echo '###' Persiapan File dan Direktori
mkdir -p destination/112233-direktur
mkdir -p source
touch source/record_20190201210022_112233.mp4
touch source/record_20190201220022_112233.mp4
touch source/record_20190301050129_4455.mp4
touch source/record_20190301060722_4455.mp4
echo '###' Cek Kondisi Sebelum
find source
find destination
VAR=$(cat <<'END_HEREDOC'
    require __DIR__ . '/../../vendor/autoload.php';
    $app = new \IjorTengab\FilesBulkOperation\Action\Reposition;
    try {
        $app->move()
            ->setWorkingDirectoryLookup('source')
            ->setWorkingDirectoryDestination('destination')
            ->setFileNamePattern('/record.*\D+(\d+)\D*\.mp4$/')
            ->setDirectoryDestinationPattern('/^$1.*/', '$1')
            ->execute();
        }
    catch (Exception $e) {
        echo $e->getMessage();
    }
END_HEREDOC
)
echo '###' Execute PHP
php -r "$VAR"
echo '###' Cek Kondisi Sesudah
find source
find destination
echo '###' Cleaning
rm -rf destination
rm -rf source
echo '###' Finish
