<?php

namespace Chiquitto\Sociodb\Action\Ibge\Municipio;

use Chiquitto\Sociodb\Action\ActionAbstract;
use Chiquitto\Sociodb\Conexao;
use PDO;
use PDOException;

/**
 * 
 *
 * @author chiquitto
 */
class RendimentoMedioMensalDomicilioUrbano extends ActionAbstract
{
    
    public function process(array $params = array())
    {
        $ufRowset = Conexao::getDoctrine()->query('SELECT cdUf, stSigla From tbsuf');
        
        while ($ufRow = $ufRowset->fetch(PDO::FETCH_ASSOC)) {
            $this->processUf($ufRow);
        }
    }

    private function processUf($uf)
    {
        $cdUf = $uf['cdUf'];
        
        $url = "http://www.cidades.ibge.gov.br/cartograma/getdata.php?coduf={$cdUf}&idtema=16&codv=V17&nfaixas=4";
        $content = file_get_contents($url);

        // @link http://stackoverflow.com/questions/6941642/php-json-decode-fails-without-quotes-on-key
        // $content = str_replace(array('"', "'"), array('\"', '"'), $content);
        $content = preg_replace('/(\w+):/i', '"\1":', $content);

        $json = json_decode($content, 1);
        
        $con = Conexao::getDoctrine();

        $sql = "Update tbibge_municipio
            Set vlRendimentoMedioMensalUrbano2010 = :vlRendimentoMedioMensalUrbano
            Where (cdUf = :cdUf) And (cdMunicipio = :cdMunicipio)";
        $stmt = $con->prepare($sql);
        $stmt->bindValue(':cdUf', $cdUf);
        
        $con->beginTransaction();

        foreach ($json['municipios'] as $cdMunicipio => $municipio) {
            $cdMunicipioOriginal = $cdMunicipio;
            $cdMunicipio = (int) substr($cdMunicipio, 2);
            
            $municipio['v'] = (float) $municipio['v'];
            if ($municipio['v'] == 0.0) {
                $municipio['v'] = null;
            }

            $stmt->bindValue(':cdMunicipio', $cdMunicipio);
            $stmt->bindValue(':vlRendimentoMedioMensalUrbano', (float) $municipio['v']);

            try {
                $stmt->execute();
            } catch (PDOException $exc) {
                echo "$cdMunicipioOriginal ($cdUf / $cdMunicipio) => ";
                print_r($municipio);
                echo $exc;
                exit;
            }
        }

        $con->commit();
    }

}
