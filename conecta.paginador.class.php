<?php
require_once 'conecta.class.php';

/**
* Classe para facilitar alterações e acesso ao Banco via PDO
*
* @name Paginador
* @author Daniel da Silva Bispo
* @webpage http://about.me/daniel.bispo
* @twitter http://twitter.com/webmaster_cn
* @facebook http://www.facebook.com/TMW.Tecnology
* @link https://docs.google.com/file/d/0B2DBrbHSolGEazBMdEM3SEZPZFU
* @link Conecta https://docs.google.com/file/d/0B2DBrbHSolGEQjdUWXhucVMtM00
* @version 2.0
* @since 10/01/2013
* @lastUpdate 21/03/2013
*/
class Paginador extends Conecta 
{

	/**
	* Variáveis a serem setadas com pagExecute()
	* @access private
	*/
	private 
		// Página Atual. 
		$pagAtual, 	
		// Número de registros por página. 
		$numRegPag;	
			
	/**
	* Variáveis de retorno informativo após $this->execute(), sem valor atuante para a classe
	* @access public
	*/
	public 	
		// Número de páginas da Query. Apenas para quando há paginação.
		$numTotPag,	
		// Primeiro registro da página atual da Query. Apnas para quando há paginação.
		$firstReg;	
	
	/**
	* Método construtor
	* @access public
	* @param string $qualTabela Nome da Tabela a ser Acessada
	*/
	public function __construct( $qualTabela = '' ) 
	{ 
		parent::__construct();
		$this->zeraVars( $qualTabela );
	}
	
	
	/**
	* Retorna as variáveis ao seu padrão.
	* @access public
	* @param string $qualTabela Nome da Tabela a ser modificada
	*/
	public function zeraVars( $qualTabela = '' ) 
	{
		parent::zeraVars( $qualTabela );
		$this->setVars( array(
			'pagAtual' => 0,
			'numRegPag' => 0,
		) );
		
	}
	
	/**
	* Executa a Query conforme configuração da paginação
	* @access public
	* @param string  $pagAtual     Página Atual
	* @param boolean $numRegPag    Número de Registros por página
	*/
	public function pagExecute( $pagAtual, $numRegPag )
	{
		if ( $this->type != '' && $this->table != '' ) {
			
			$this->erro = '';
			
			try	
			{
				// Inicia SQL com o tipo
				$this->sqlQuery = $this->type; 
				
				if ( strtoupper( $this->type ) != 'SELECT' ) {
					$this->erro = 'Usar apenas com SELECT';
					return false;
				}
				
				$this->pagAtual = ( $pagAtual > 0 ) ? $pagAtual : 1;
				$this->numRegPag = ( $numRegPag > 0 ) ? $numRegPag : 10;

				// "SELECT"
				$this->sqlQuery .= " " . ( ( $this->selQual != '' ) ? $this->selQual : "*" ) . " FROM " . $this->table . " " . $this->qJoin . " ";
				
				// Conferindo where
				if ( $this->where != '' ) 
					$this->sqlQuery .= "WHERE " . $this->where . " ";
					
				//Conferindo order
				if ( $this->order != '' ) 
					$this->sqlQuery .= "ORDER BY " . $this->order . " ";
					
				// Preparando Query
				$query = $this->pdoConecta->prepare( $this->sqlQuery );

				// Conferindo campos com bindValue
				foreach ( $this->campos as $chave => $valor ) 
					// Valor Nulo
					if ( $valor == NULL )
						$query->bindValue( ":" . preg_replace( "/\*/", "", $chave ), $valor, PDO::PARAM_NULL );
					// Valor Integer
					else if ( is_integer( $valor ) ) 
						$query->bindValue( ":" . preg_replace( "/\*/", "", $chave ), $valor, PDO::PARAM_INT );
					// Outros Valores
					else 
						// Analisa se está previsto valores HTML (* no campo)
						if ( preg_match( '/\*/', $chave ) ) 
							$query->bindValue( ":" . preg_replace( "/\*/", "", $chave ), $valor, PDO::PARAM_STR );
						// Se não passou em nenhum dos testes anteriores, elimina qq/ traço de cóidigo no valor
						else
							$query->bindValue( ":" . $chave, strip_tags( trim( $valor ) ), PDO::PARAM_STR );

				// Corrigindo Query para Análise
				$this->sql = $this->sqlQuery;
				foreach ( $this->campos as $chave => $valor ) {
					if ( preg_match( '/\*/', $chave ) ) {
						$valor = htmlentities( utf8_decode( $valor ) );
						$chave = preg_replace( '/\*/', '', $chave );
					}
					$regex = "/:$chave/";
					$subst = "'$valor'";
					$this->sql = preg_replace( $regex, $subst, $this->sql );
				}

				//Executando Query
				$query->execute();
				
				// Numero de registros afetados
				$this->numRegs = $query->rowCount();
				
				if ( $this->type == "INSERT" || $this->type == "REPLACE" )
					// Último registro inserido
					$this->ultimoReg = $this->pdoConecta->lastInsertId();

				if ( $this->type == "SELECT" ) {
					// Para alguns tipos de BD, rowCount não funciona com SELECT
					if ( $this->numRegs == 0 ) $this->numRegs = count( $query->fetchAll() ); 
					
					// Calcula paginação
					if ( $this->numRegPag > 0 && $this->pagAtual > 0 ) {
						
						$this->numTotPag = ceil( $this->numRegs / $this->numRegPag );
						
						// Se houver uma entrada inválida, ele corrige
						if ( $this->pagAtual > $this->numTotPag ) $this->pagAtual = $this->numTotPag; 
						$this->firstReg = ( $this->pagAtual - 1 ) * $this->numRegPag;
						
						$this->sqlQuery .= "LIMIT " . $this->firstReg . "," . $this->numRegPag;

						// Executa a query novamente para limitar os registros
						$query = $this->pdoConecta->prepare( $this->sqlQuery );
						foreach ( $this->campos as $chave => $valor )
							if ( eregi('\*', $chave) ) 
								$query->bindValue( ":" . preg_replace( "/\*/", "", $chave ), $valor, PDO::PARAM_STR );
							else
								$query->bindValue( ":" . preg_replace( "/\*/", "", $chave ), strip_tags( trim( $valor ) ), PDO::PARAM_STR );	

						// Corrigindo Query para Análise com Paginação
						$this->sql = $this->sqlQuery;
						foreach ( $this->campos as $chave => $valor ) {
							if ( preg_match( '/\*/', $chave ) ) {
								$valor = htmlentities( utf8_decode( $valor ) );
								$chave = preg_replace( '/\*/', '', $chave );
							}
							$regex = "/:$chave/";
							$subst = "'$valor'";
							$this->sql = preg_replace( $regex, $subst, $this->sql );
						}

						$query->execute();
					}
					
					//Retorno diferenciado por tipo de consulta
					if ( $this->oneRow )
						// Retorna apenas um único registro no formato de objeto
						return $query->fetchObject(); 
					else
						// Retorna um array com todos os registros da consulta, cada linha sendo um objeto
						return $query->fetchAll( PDO::FETCH_OBJ ); 

				} else 
					return true;
	
			} 
			catch ( PDOException $error_query ) 
			{
				$this->erro = 'Erro obtido em ' . date( 'd/m/Y H:i:s' ) . '(' . $error_query->getCode() . '): ' . $error_query->getMessage()
							. ' | SQL -> ' . $this->sql;
				return false;
			}
			
		} else if ( $sql != '' ) {
			
			$this->erro = '';
			
			// Execução de um código SQL direto
			try
			{
				$query = $this->pdoConecta->query( $sql );
				
				//Retorno diferenciado por tipo de consulta
				if ( $oneRow )
					// Retorna apenas um único registro no formato de objeto
					return $query->fetchObject(); 
				else
					// Retorna um array com todos os registros da consulta, cada linha sendo um objeto
					return $query->fetchAll( PDO::FETCH_OBJ ); 

			} 
			catch ( PDOException $error_query ) 
			{
				$this->erro = 'Erro obtido em ' . date( 'd/m/Y H:i:s' ) . '(' . $error_query->getCode() . '): ' . $error_query->getMessage()
							. ' | SQL -> ' . $sql;
				return false;
			}	
					
		} else {
			
			$this->erro = 'Parâmetros insuficientes';
			return false;
		
		}
	}
	
	/**
	* Coloca os links de paginação
	* @access public
	* @param integer $limitLinks Limite de links ao redor da página atual. Deixar vazio ou 0 (zero) para ilimitado.
	* @param integer $urlX		 Url de destino. Deixar vazio para mesma página. 
	*							 Usar '%pagAtual%' onde desejar que na url apareça a página atual.
	*							 Ex.: $pdo->paginar( 4, '/lista/%pagAtual%/', );
	* @param integer $div		 Divisória. Padrão = '|'
	* @param integer $dig		 Quantos dígitos terá a paginação. Padrão = 2.
	*/
	public function paginar( $limitLinks = 10, $urlX = '', $div = '|', $dig = 2 ) 
	{
		echo '<ul class="paginador">';
	
		// Loop até total de páginas
		for ( $x=1; $x <= $this->numTotPag; $x++ ) {

			if ( preg_match( '/%pagAtual%/', $urlX ) )
				$url = preg_replace( '/%pagAtual%/', $x, $urlX ); 
			else
				$url = "$urlX?pagAtual=$x";
			
			$pag = str_pad( $x, $dig, '0', STR_PAD_LEFT );
			$pri = str_pad( '1', $dig, '0', STR_PAD_LEFT );
			$ult = str_pad( $this->numTotPag, $dig, '0', STR_PAD_LEFT );
		
			// Link para página atual
			if ( $x == $this->pagAtual ) {
	
				echo "<li class='atu'>{$pag}</li>";
	
			// Link para 1a página
			} else if ( $x == 1 && $limitLinks != 0 && $limitLinks < $this->numTotPag ) {
	
				echo "<li class='pri'><a class='link' href='$url'>$pri</a></li>"
				   . "<li class='div'>$div</li>";
	
			// Link para última página
			} else if ( $x == $this->numTotPag && $limitLinks != 0 && $limitLinks < $this->numTotPag ) {
	
				echo "<li class='div'>$div</li>"
				   . "<li class='ult'><a class='link' href='$url'>$ult</a></li>";
	
			// Demais links quando NÃO há limitação
			} else if ( $limitLinks == 0 ) {
	
				echo "<li class='num'><a class='link' href='$url'>$pag</a></li>";
	
			// demais links quando há limitação
			} else if ( ( $x < $this->pagAtual + ceil( $limitLinks / 2 ) && $x > $this->pagAtual - ceil( $limitLinks / 2 ) ) || 
						( $this->pagAtual < ( ceil( $limitLinks / 2 ) + 2 ) && $x < ( $limitLinks + 1 ) )  ||
						( $this->pagAtual > $this->numTotPag - ( ceil( $limitLinks / 2 ) + 1 ) && $x > $this->numTotPag - $limitLinks ) ) {
	
				echo "<li class='num'><a class='link' href='$url'>$pag</a></li>";
	
			}
		}
		
		echo '</ul>';
		
	}
	
	/**
	* Coloca os itens de paginação, utilizando <form> (Ótimo para uso de AJAX)
	* @access public
	* @param string  $idForm 	 Id do <form> a ser gerado.
	* @param integer $limitLinks Limite de links ao redor da página atual. Deixar vazio ou 0 (zero) para ilimitado.
	* @param string  $url 		 Url de destino. Deixar vazio para mesma página.
	* @param integer $div		 Divisória. Padrão = '|'
	* @param integer $dig		 Quantos dígitos terá a paginação. Padrão = 2.
	*/
	public function paginarForm( $idForm = 'frm_paginar', $limitLinks = 10, $url = '', $div = '|', $dig = 2 ) 
	{
		echo '<!--INICIO PAGINAÇÃO--><ul class="paginador formulario">';

		// Loop até total de páginas
		for ( $x=1; $x <= $this->numTotPag; $x++ ) {
			
			// Link para página atual
			if ( $x == $this->pagAtual ) {

				echo '<li class="prox"><form id="' . $idForm . '" name="' . $idForm . '" action="' . $url 
				   . '" method="post" class="form"><input id="pagAtual_' . $idForm . '" name="pagAtual" type="hidden" value="' 
				   . ( ( ( $x ) >= $this->numTotPag ) ? 1 : ( $x + 1 ) )
				   . '" /><input type="text" value="' . str_pad( $x, $dig, '0', STR_PAD_LEFT ) . '" maxlength="' . $dig 
				   . '" onKeyDown="var tecla = (window.event) ? event.keyCode : e.which; '
				   . 'if ( ( tecla > 47 && tecla < 58 ) || ( tecla > 95 && tecla < 106 ) || tecla == 8 || tecla == 13 ) { '
				   . ' document.getElementById(\'pagAtual_' . $idForm
				   . '\').value = this.value; return true; } else return false;" class="text" />'
				   . '<input type="submit" class="avanc" value="" id="avanc_' . $idForm . '" /></form></li>';

			// Link para 1a página
			} else if ( $x == 1 && $limitLinks != 0 && $limitLinks < $this->numTotPag ) {

				echo '<li class="pri"><a class="link" href="javascript:void(0);" onclick="'
				   . 'document.getElementById(\'pagAtual_' . $idForm . '\').value = 1'
				   . '; document.getElementById(\'avanc_' . $idForm . '\').click();">' . str_pad( 1, $dig, '0', STR_PAD_LEFT ) . '</a></li>'
				   . '<li class="div">' . $div . '</li>';

			// Link para última página
			} else if ( $x == $this->numTotPag && $limitLinks != 0 && $limitLinks < $this->numTotPag ) {

				echo '<li class="div">'. $div . '</li>';
				echo '<li class="ult"><a class="link" href="javascript:void(0);" onclick="document.getElementById(\'pagAtual_' 
				   . $idForm . '\').value = ' . $this->numTotPag.'; document.getElementById(\'avanc_' . $idForm . '\').click();">' 
				   . str_pad( $x, $dig, '0', STR_PAD_LEFT ) . '</a></li>';

			// Demais links quando NÃO há limitação
			} else if ( $limitLinks == 0 ) {

				echo '<li class="num"><a class="link" href="javascript:void(0);" onclick="document.getElementById(\'pagAtual_' 
				. $idForm . '\').value = ' . $x . '; document.getElementById(\'avanc_' . $idForm . '\').click();">' 
				. str_pad( $x, $dig, '0', STR_PAD_LEFT ) . '</a></li>';

			// demais links quando há limitação
			} else if ( ( $x < $this->pagAtual + ceil( $limitLinks / 2 ) && $x > $this->pagAtual - ceil( $limitLinks / 2 ) ) || 
						( $this->pagAtual < ( ceil( $limitLinks / 2 ) + 2 ) && $x < ( $limitLinks + 1 ) )  ||
						( $this->pagAtual > $this->numTotPag - ( ceil( $limitLinks / 2 ) + 1 ) && $x > $this->numTotPag - $limitLinks ) ) {

				echo '<li class="num"><a class="link" href="javascript:void(0);" onclick="document.getElementById(\'pagAtual_' 
				   . $idForm . '\').value = ' . $x . '; document.getElementById(\'avanc_' . $idForm . '\').click();">' 
				   . str_pad( $x, $dig, '0', STR_PAD_LEFT ) . '</a></li>';
			}
		}
		
		echo '</ul><!--FIM PAGINAÇÃO-->';

	} 

	/**
	* Resultado Final da Paginação para o comando $pdo->paginar( 4 ); estando na página 5 de 9:
	*
	*
	* <!--INICIO PAGINAÇÃO-->
	*	<ul class="paginador">
	*		<li class="pri"><a class="link" href="?pagAtual=1">01</a></li>
	*		<li class="div">|</li>
	*		<li class="num"><a class="link" href="?pagAtual=4">04</a></li>
	*		<li class="atu">05</li>
	*		<li class="num"><a class="link" href="?pagAtual=6">06</a></li>
	*		<li class="div">|</li>
	*		<li class="ult"><a class="link" href="?pagAtual=9">9</a></li>
	*	</ul>
	* <!--FIM PAGINAÇÃO-->
	*
	* * * *
	*
	* Elementos para estilização CSS:
	*
	* .paginador {}
	* .paginador .num {}
	* .paginador .pri {}
	* .paginador .ult {}
	* .paginador .div {}
	*
	*/
		
	/**
	* Resultado Final da Paginação para o comando $pdo->paginarForm( 'pagAtual_form_paginar_header', 4, '?pg=produto' ); estando na página 5 de 9:
	*
	*
	* <!--INICIO PAGINAÇÃO-->
	*	<ul class="paginador formulario">
	*		<li class="pri">
	*			<a class="link" href="javascript:void(0);" onclick="
	*				document.getElementById('pagAtual_form_paginar_header').value = 1; 
	*				document.getElementById('avanc_form_paginar_header').click();
	*			">01</a>
	*		</li>
	*		<li class="div">|</li>
	*		<li class="num">
	*			<a class="link" href="javascript:void(0);" onclick="
	*				document.getElementById('pagAtual_form_paginar_header').value = 4; 
	*				document.getElementById('avanc_form_paginar_header').click();
	*			">04</a>
	*		</li>
	*		<li class="prox">
	*			<form id="form_paginar_header" name="form_paginar_header" action="?pg=produto" method="post" class="form">
	*				<input id="pagAtual_form_paginar_header" name="pagAtual" type="hidden" value="6">
	*				<input type="text" value="05" maxlength="2" onkeydown="
	*					var tecla = (window.event) ? event.keyCode : e.which; 
	*					if ( ( tecla > 47 && tecla < 58 ) || ( tecla > 95 && tecla < 106 ) || tecla == 8 || tecla == 13 ) {  
	*						document.getElementById('pagAtual_form_paginar_header').value = this.value; 
	*						return true; 
	*					} else 
	*						return false;
	*				" class="text">
	*				<input type="submit" class="avanc" value="" id="avanc_form_paginar_header">
	*			</form>
	*		</li>
	*		<li class="num">
	*			<a class="link" href="javascript:void(0);" onclick="
	*				document.getElementById('pagAtual_form_paginar_header').value = 6; 
	*				document.getElementById('avanc_form_paginar_header').click();
	*			">06</a>
	*		</li>
	*		<li class="div">|</li>
	*		<li class="ult">
	*			<a class="link" href="javascript:void(0);" onclick="
	*				document.getElementById('pagAtual_form_paginar_header').value = 49; 
	*				document.getElementById('avanc_form_paginar_header').click()
	*			;">49</a>
	*		</li>
	*	</ul>
	* <!--FIM PAGINAÇÃO-->
	*
	* * * *
	*
	* Elementos para estilização CSS:
	*
	* .paginador {} ou .formulario {} ou .paginador.formulario {}
	* .paginador .lista {}
	* .paginador .lista .num {}
	* .paginador .lista .pri {}
	* .paginador .lista .ult {}
	* .paginador .lista .div {}
	* .paginador .lista .prox {}
	* .paginador .lista .prox .form {}
	* .paginador .lista .prox .form .text {}
	* .paginador .lista .prox .form .avanc {}
	*
	*/

}
?>