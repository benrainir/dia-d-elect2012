<?php
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Content-type: application/json');

include "aplicacao/boot.php"; 

$acao= aplicacao::getParam("acao");
$json = null;

if ($acao == "carregar-mapa"){
	$sql = "SELECT UCASE(s1.local) as local, s1.latitude, s1.longitude, 1 as tipo, 
				COALESCE(( select count(*) 
				  from secao s2
				  inner join advogado_secao a  ON s2.secao = a.secao AND s2.zona = a.zona
				  where md5(s2.local) = md5(s1.local)
				  group by s2.local
				),0) as total_adv , md5(s1.local) as cod_local
			FROM secao s1 GROUP BY s1.local, s1.latitude, s1.longitude";
		
	$value = banco::listar($sql);
	$json = json_encode($value);
}

if ($acao == "lista-advogados"){
	$md5Local = aplicacao::getParam("local");
		
	$sql = "SELECT cod_advogado, UCASE(nome) as nome,
				COALESCE(( select count(*)
						   from secao s
						   inner join advogado_secao asec  ON s.secao = asec.secao AND s.zona = asec.zona
						   where md5(s.local) = ? and asec.cod_advogado = a.cod_advogado
						   group by s.local
				),0) as total
			FROM advogado a ORDER BY total ASC, nome ASC";
	$value = banco::listar($sql,array($md5Local));
	$json = json_encode($value);
}

if($acao == "associar-advogados"){
	$values="";
	$sql="";
	$resultado = array("sucesso"=>0);
	$localSelecionado = urldecode(aplicacao::getParam('local'));
	if ($localSelecionado){
		$secaoResult = banco::listar("SELECT * FROM secao WHERE local = ? ",array($localSelecionado));
	}	
			
	if ( count($secaoResult) > 0){			
		banco::abrirTransacao();
		try{			
			//DELETAR OS REGISTROS DE Advogados associados ao LOCAL
			foreach ($secaoResult as $secao){					
				$sql="DELETE FROM advogado_secao WHERE secao = ? and zona = ?";
				banco::executar($sql,array($secao->secao,$secao->zona));
			}		

			$listaAdvg = aplicacao::getParam('listaCodAdvg');
			if ($listaAdvg){
				$lista = explode(",", $listaAdvg);
				if(count($lista) > 0){
					//Associar para cada advogado todas as secoes do local
					foreach ($lista as $cod_adv) {
						foreach ($secaoResult as $secao){
							//DELETAR OS REGISTROS DE Advogados associados ao LOCAL
							$sql="INSERT INTO advogado_secao (cod_advogado,secao,zona) VALUES (?,?,?)";
							banco::executar($sql,array($cod_adv, $secao->secao,$secao->zona));
						}
					}
				}			
			}
			$resultado = array("sucesso"=>1);
			
		}catch(excption $e){
			$resultado = array("sucesso"=>0);
			banco::cancelarTransacao();
		}
		banco::fecharTransacao();
	}
	
	$json = json_encode($resultado);
}

print $json;