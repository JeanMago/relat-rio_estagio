<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plano;
use Illuminate\Http\Request;

class PlanoController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = Plano::query()->orderBy('nome');

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('nome', 'like', "%{$search}%")
                    ->orWhere('descricao', 'like', "%{$search}%")
                    ->orWhereJsonContains('modulos', $search);
            });
        }

        if (isset($filters['status'])) {
            $query->where('status', (int) $filters['status']);
        }

        $rows = $query->paginate((int) ($filters['limit'] ?? 30));

        return $this->successList($rows);
    }

    public function store(Request $request)
    {
        if ($request->user()->perfil !== 'super_admin') {
            throw new \Illuminate\Auth\Access\AuthorizationException('Ação exclusiva para Super Admin.');
        }

        $plano = Plano::query()->create($this->validatePayload($request));
        return $this->successItem($plano->fresh(), 'Plano criado com sucesso.', 201);
    }

    public function show(string $id)
    {
        return $this->successItem(Plano::query()->findOrFail($id));
    }

    public function update(Request $request, string $id)
    {
        if ($request->user()->perfil !== 'super_admin') {
            throw new \Illuminate\Auth\Access\AuthorizationException('Ação exclusiva para Super Admin.');
        }

        $plano = Plano::query()->findOrFail($id);
        $plano->update($this->validatePayload($request, true));
        return $this->successItem($plano->fresh(), 'Plano atualizado com sucesso.');
    }

    public function destroy(string $id)
    {
        if (request()->user()->perfil !== 'super_admin') {
            throw new \Illuminate\Auth\Access\AuthorizationException('Ação exclusiva para Super Admin.');
        }

        $plano = Plano::query()->findOrFail($id);
        $plano->delete();

        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => null,
            'message' => 'Plano removido com sucesso.',
            'errors' => null,
            'meta' => null,
        ]);
    }

    /**
     * Retorna a lista de módulos disponíveis no sistema para seleção na UI.
     */
    public function availableModules()
    {
        return response()->json([
            'status' => true,
            'data' => [
                ['id' => 'vPessoa', 'label' => 'Pessoas • Visualizar', 'description' => 'Permite visualizar o cadastro de pessoas'],
                ['id' => 'aPessoa', 'label' => 'Pessoas • Adicionar', 'description' => 'Permite adicionar novas pessoas'],
                ['id' => 'ePessoa', 'label' => 'Pessoas • Editar', 'description' => 'Permite editar cadastros de pessoas'],
                ['id' => 'dPessoa', 'label' => 'Pessoas • Excluir', 'description' => 'Permite excluir cadastros de pessoas'],
                ['id' => 'uPessoa', 'label' => 'Pessoas • Unificar', 'description' => 'Permite unificar cadastros duplicados'],
                ['id' => 'vUsuario', 'label' => 'Usuarios • Visualizar', 'description' => 'Permite visualizar usuários do sistema'],
                ['id' => 'aUsuario', 'label' => 'Usuarios • Adicionar', 'description' => 'Permite adicionar novos usuários'],
                ['id' => 'eUsuario', 'label' => 'Usuarios • Editar', 'description' => 'Permite editar usuários existentes'],
                ['id' => 'dUsuario', 'label' => 'Usuarios • Excluir', 'description' => 'Permite excluir usuários'],
                ['id' => 'vMeusdados', 'label' => 'Meus Dados • Visualizar', 'description' => 'Permite visualizar o próprio perfil'],
                ['id' => 'aMeusdados', 'label' => 'Meus Dados • Adicionar', 'description' => 'Permite adicionar informações ao perfil'],
                ['id' => 'eMeusdados', 'label' => 'Meus Dados • Editar', 'description' => 'Permite editar o próprio perfil'],
                ['id' => 'dMeusdados', 'label' => 'Meus Dados • Excluir', 'description' => 'Permite remover informações do perfil'],
                ['id' => 'vListaPreco', 'label' => 'Lista de Preços • Visualizar', 'description' => 'Permite visualizar listas de preços'],
                ['id' => 'aListaPreco', 'label' => 'Lista de Preços • Adicionar', 'description' => 'Permite criar listas de preços'],
                ['id' => 'eListaPreco', 'label' => 'Lista de Preços • Editar', 'description' => 'Permite editar listas de preços'],
                ['id' => 'dListaPreco', 'label' => 'Lista de Preços • Excluir', 'description' => 'Permite excluir listas de preços'],
                ['id' => 'vCategoriaConta', 'label' => 'Categorias de Contas • Visualizar', 'description' => 'Permite visualizar categorias financeiras'],
                ['id' => 'aCategoriaConta', 'label' => 'Categorias de Contas • Adicionar', 'description' => 'Permite criar categorias financeiras'],
                ['id' => 'eCategoriaConta', 'label' => 'Categorias de Contas • Editar', 'description' => 'Permite editar categorias financeiras'],
                ['id' => 'dCategoriaConta', 'label' => 'Categorias de Contas • Excluir', 'description' => 'Permite excluir categorias financeiras'],
                ['id' => 'vCategoriaVenda', 'label' => 'Categorias de Vendas • Visualizar', 'description' => 'Permite visualizar categorias de vendas'],
                ['id' => 'aCategoriaVenda', 'label' => 'Categorias de Vendas • Adicionar', 'description' => 'Permite criar categorias de vendas'],
                ['id' => 'eCategoriaVenda', 'label' => 'Categorias de Vendas • Editar', 'description' => 'Permite editar categorias de vendas'],
                ['id' => 'dCategoriaVenda', 'label' => 'Categorias de Vendas • Excluir', 'description' => 'Permite excluir categorias de vendas'],
                ['id' => 'vVeiculos', 'label' => 'Veículos • Visualizar', 'description' => 'Permite visualizar o cadastro de veículos'],
                ['id' => 'aVeiculos', 'label' => 'Veículos • Adicionar', 'description' => 'Permite adicionar novos veículos'],
                ['id' => 'eVeiculos', 'label' => 'Veículos • Editar', 'description' => 'Permite editar cadastros de veículos'],
                ['id' => 'dVeiculos', 'label' => 'Veículos • Excluir', 'description' => 'Permite excluir cadastros de veículos'],
                ['id' => 'vFormaPagamento', 'label' => 'Formas de Pagamento • Visualizar', 'description' => 'Permite visualizar formas de pagamento'],
                ['id' => 'aFormaPagamento', 'label' => 'Formas de Pagamento • Adicionar', 'description' => 'Permite criar formas de pagamento'],
                ['id' => 'eFormaPagamento', 'label' => 'Formas de Pagamento • Editar', 'description' => 'Permite editar formas de pagamento'],
                ['id' => 'dFormaPagamento', 'label' => 'Formas de Pagamento • Excluir', 'description' => 'Permite excluir formas de pagamento'],
                ['id' => 'vEmpresa', 'label' => 'Empresas • Visualizar', 'description' => 'Permite visualizar dados da empresa'],
                ['id' => 'aEmpresa', 'label' => 'Empresas • Adicionar', 'description' => 'Permite cadastrar empresa'],
                ['id' => 'eEmpresa', 'label' => 'Empresas • Editar', 'description' => 'Permite editar dados da empresa'],
                ['id' => 'dEmpresa', 'label' => 'Empresas • Excluir', 'description' => 'Permite excluir dados da empresa'],
                ['id' => 'vProduto', 'label' => 'Produto • Visualizar', 'description' => 'Permite visualizar o cadastro de produtos'],
                ['id' => 'aProduto', 'label' => 'Produto • Adicionar', 'description' => 'Permite adicionar novos produtos'],
                ['id' => 'eProduto', 'label' => 'Produto • Editar', 'description' => 'Permite editar cadastros de produtos'],
                ['id' => 'dProduto', 'label' => 'Produto • Excluir', 'description' => 'Permite excluir cadastros de produtos'],
                ['id' => 'adProduto', 'label' => 'Produto • Alterar Depósito', 'description' => 'Permite alterar o depósito dos produtos'],
                ['id' => 'vDeposito', 'label' => 'Deposito • Visualizar', 'description' => 'Permite visualizar depósitos/estoques'],
                ['id' => 'aDeposito', 'label' => 'Deposito • Adicionar', 'description' => 'Permite criar novos depósitos'],
                ['id' => 'eDeposito', 'label' => 'Deposito • Editar', 'description' => 'Permite editar depósitos'],
                ['id' => 'dDeposito', 'label' => 'Deposito • Excluir', 'description' => 'Permite excluir depósitos'],
                ['id' => 'vMovimentacao', 'label' => 'Movimentações • Visualizar', 'description' => 'Permite visualizar movimentações de estoque'],
                ['id' => 'aMovimentacao', 'label' => 'Movimentações • Adicionar', 'description' => 'Permite criar movimentações de estoque'],
                ['id' => 'eMovimentacao', 'label' => 'Movimentações • Editar', 'description' => 'Permite editar movimentações'],
                ['id' => 'dMovimentacao', 'label' => 'Movimentações • Excluir', 'description' => 'Permite excluir movimentações'],
                ['id' => 'vEstoquePendente', 'label' => 'Movimentações • Estoque Pendente Fiscal', 'description' => 'Permite visualizar estoque pendente'],
                ['id' => 'vServico', 'label' => 'Serviço • Visualizar', 'description' => 'Permite visualizar o cadastro de serviços'],
                ['id' => 'aServico', 'label' => 'Serviço • Adicionar', 'description' => 'Permite adicionar novos serviços'],
                ['id' => 'eServico', 'label' => 'Serviço • Editar', 'description' => 'Permite editar cadastros de serviços'],
                ['id' => 'dServico', 'label' => 'Serviço • Excluir', 'description' => 'Permite excluir cadastros de serviços'],
                ['id' => 'vGarantia', 'label' => 'Garantia • Visualizar', 'description' => 'Permite visualizar termos de garantia'],
                ['id' => 'aGarantia', 'label' => 'Garantia • Adicionar', 'description' => 'Permite criar termos de garantia'],
                ['id' => 'eGarantia', 'label' => 'Garantia • Editar', 'description' => 'Permite editar termos de garantia'],
                ['id' => 'dGarantia', 'label' => 'Garantia • Excluir', 'description' => 'Permite excluir termos de garantia'],
                ['id' => 'vCategoria', 'label' => 'Categorias de Produto • Visualizar', 'description' => 'Permite visualizar categorias de produto'],
                ['id' => 'aCategoria', 'label' => 'Categorias de Produto • Adicionar', 'description' => 'Permite adicionar categorias de produto'],
                ['id' => 'eCategoria', 'label' => 'Categorias de Produto • Editar', 'description' => 'Permite editar categorias de produto'],
                ['id' => 'dCategoria', 'label' => 'Categorias de Produto • Excluir', 'description' => 'Permite excluir categorias de produto'],
                ['id' => 'vMarca', 'label' => 'Marcas • Visualizar', 'description' => 'Permite visualizar marcas'],
                ['id' => 'aMarca', 'label' => 'Marcas • Adicionar', 'description' => 'Permite adicionar marcas'],
                ['id' => 'eMarca', 'label' => 'Marcas • Editar', 'description' => 'Permite editar marcas'],
                ['id' => 'dMarca', 'label' => 'Marcas • Excluir', 'description' => 'Permite excluir marcas'],
                ['id' => 'vCalendario', 'label' => 'Calendário / Agenda • Visualizar', 'description' => 'Permite visualizar calendário / agenda'],
                ['id' => 'aCalendario', 'label' => 'Calendário / Agenda • Adicionar', 'description' => 'Permite adicionar compromissos'],
                ['id' => 'eCalendario', 'label' => 'Calendário / Agenda • Editar', 'description' => 'Permite editar compromissos'],
                ['id' => 'dCalendario', 'label' => 'Calendário / Agenda • Excluir', 'description' => 'Permite excluir compromissos'],
                ['id' => 'aChamado', 'label' => 'Chamado • Adicionar', 'description' => 'Permite abrir novos chamados'],
                ['id' => 'vChamado', 'label' => 'Chamado • Visualizar', 'description' => 'Permite visualizar chamados'],
                ['id' => 'eChamado', 'label' => 'Chamado • Editar', 'description' => 'Permite editar chamados'],
                ['id' => 'dChamado', 'label' => 'Chamado • Excluir', 'description' => 'Permite excluir chamados'],
                ['id' => 'exChamado', 'label' => 'Chamado • Executa Chamado', 'description' => 'Permite executar/atender chamados'],
                ['id' => 'encChamado', 'label' => 'Chamado • Encerrar', 'description' => 'Permite encerrar chamados'],
                ['id' => 'fatChamado', 'label' => 'Chamado • Faturar Chamado', 'description' => 'Permite faturar chamados'],
                ['id' => 'atChamado', 'label' => 'Chamado • Alterar Técnico', 'description' => 'Permite trocar o técnico do chamado'],
                ['id' => 'techamado', 'label' => 'Chamado • Técnico Específico', 'description' => 'Permissões específicas para técnicos'],
                ['id' => 'vChamadoLogs', 'label' => 'Chamado • Ver Logs', 'description' => 'Permite ver histórico do chamado'],
                ['id' => 'aOS', 'label' => 'OS • Adicionar', 'description' => 'Permite abrir novas ordens de serviço'],
                ['id' => 'vOS', 'label' => 'OS • Visualizar', 'description' => 'Permite visualizar ordens de serviço'],
                ['id' => 'eOS', 'label' => 'OS • Editar', 'description' => 'Permite editar ordens de serviço'],
                ['id' => 'dOS', 'label' => 'OS • Excluir', 'description' => 'Permite excluir ordens de serviço'],
                ['id' => 'encOS', 'label' => 'OS • Encerrar', 'description' => 'Permite encerrar ordens de serviço'],
                ['id' => 'atOS', 'label' => 'OS • Alterar Técnico', 'description' => 'Permite trocar o técnico da OS'],
                ['id' => 'epOS', 'label' => 'OS • Empresta Produto', 'description' => 'Permite vincular produtos emprestados'],
                ['id' => 'vOSLogs', 'label' => 'OS • Ver Logs', 'description' => 'Permite ver histórico da OS'],
                ['id' => 'aPedido', 'label' => 'Pedido • Adicionar', 'description' => 'Permite criar novos pedidos'],
                ['id' => 'vPedido', 'label' => 'Pedido • Visualizar', 'description' => 'Permite visualizar pedidos'],
                ['id' => 'ePedido', 'label' => 'Pedido • Editar', 'description' => 'Permite editar pedidos'],
                ['id' => 'dPedido', 'label' => 'Pedido • Excluir', 'description' => 'Permite excluir pedidos'],
                ['id' => 'aEmprestimo', 'label' => 'Empréstimos • Adicionar', 'description' => 'Permite criar novos empréstimos'],
                ['id' => 'vEmprestimo', 'label' => 'Empréstimos • Visualizar', 'description' => 'Permite visualizar empréstimos'],
                ['id' => 'eEmprestimo', 'label' => 'Empréstimos • Editar', 'description' => 'Permite editar empréstimos'],
                ['id' => 'dEmprestimo', 'label' => 'Empréstimos • Excluir', 'description' => 'Permite excluir empréstimos'],
                ['id' => 'aVenda', 'label' => 'Vendas • Adicionar', 'description' => 'Permite realizar novas vendas'],
                ['id' => 'vVenda', 'label' => 'Vendas • Visualizar', 'description' => 'Permite visualizar vendas'],
                ['id' => 'eVenda', 'label' => 'Vendas • Editar', 'description' => 'Permite editar vendas'],
                ['id' => 'dVenda', 'label' => 'Vendas • Excluir', 'description' => 'Permite excluir vendas'],
                ['id' => 'vPdv', 'label' => 'Vendas • PDV', 'description' => 'Acesso ao Ponto de Venda'],
                ['id' => 'chRelatorio', 'label' => 'Relatório • Chamado', 'description' => 'Relatórios de chamados'],
                ['id' => 'clRelatorio', 'label' => 'Relatório • Cliente', 'description' => 'Relatórios de clientes'],
                ['id' => 'osRelatorio', 'label' => 'Relatório • OS', 'description' => 'Relatórios de OS'],
                ['id' => 'peRelatorio', 'label' => 'Relatório • Pedido', 'description' => 'Relatórios de pedidos'],
                ['id' => 'proRelatorio', 'label' => 'Relatório • Produto', 'description' => 'Relatórios de produtos'],
                ['id' => 'gaRelatorio', 'label' => 'Relatório • Garantia', 'description' => 'Relatórios de garantias'],
                ['id' => 'seRelatorio', 'label' => 'Relatório • Serviço', 'description' => 'Relatórios de serviços'],
                ['id' => 'fiRelatorio', 'label' => 'Relatório • Financeiro', 'description' => 'Relatórios financeiros'],
                ['id' => 'veRelatorio', 'label' => 'Relatório • Venda', 'description' => 'Relatórios de vendas'],
                ['id' => 'ctRelatorio', 'label' => 'Relatório • Comissão Técnico', 'description' => 'Relatórios de comissão'],
                ['id' => 'aArquivo', 'label' => 'Arquivo • Adicionar', 'description' => 'Permite anexar arquivos'],
                ['id' => 'vArquivo', 'label' => 'Arquivo • Visualizar', 'description' => 'Permite visualizar arquivos anexados'],
                ['id' => 'eArquivo', 'label' => 'Arquivo • Editar', 'description' => 'Permite editar informações de arquivos'],
                ['id' => 'dArquivo', 'label' => 'Arquivo • Excluir', 'description' => 'Permite remover arquivos'],
                ['id' => 'vPermissao', 'label' => 'Permissão • Visualizar', 'description' => 'Permite ver grupos de permissão'],
                ['id' => 'aPermissao', 'label' => 'Permissão • Adicionar', 'description' => 'Permite criar grupos de permissão'],
                ['id' => 'ePermissao', 'label' => 'Permissão • Editar', 'description' => 'Permite editar grupos de permissão'],
                ['id' => 'dPermissao', 'label' => 'Permissão • Excluir', 'description' => 'Permite excluir grupos de permissão'],
                ['id' => 'aStatus', 'label' => 'Status • Adicionar', 'description' => 'Permite criar novos status'],
                ['id' => 'vStatus', 'label' => 'Status • Visualizar', 'description' => 'Permite ver a lista de status'],
                ['id' => 'eStatus', 'label' => 'Status • Editar', 'description' => 'Permite editar status existentes'],
                ['id' => 'dStatus', 'label' => 'Status • Excluir', 'description' => 'Permite excluir status'],
                ['id' => 'aConfiguracao', 'label' => 'Configurações • Adicionar', 'description' => 'Permite adicionar configurações'],
                ['id' => 'vConfiguracao', 'label' => 'Configurações • Visualizar', 'description' => 'Permite ver as configurações do sistema'],
                ['id' => 'eConfiguracao', 'label' => 'Configurações • Editar', 'description' => 'Permite alterar as configurações do sistema'],
                ['id' => 'dConfiguracao', 'label' => 'Configurações • Excluir', 'description' => 'Permite remover configurações'],
                ['id' => 'impConfiguracao', 'label' => 'Configurações • Impressoras', 'description' => 'Configurações de impressão'],
                ['id' => 'pConfiguracao', 'label' => 'Configurações • Permissões', 'description' => 'Configurações de permissões'],
                ['id' => 'acessoMaster', 'label' => 'Configurações • MASTER', 'description' => 'Acesso total ao sistema'],
                ['id' => 'vImpressora', 'label' => 'Impressoras • Visualizar', 'description' => 'Visualizar impressoras cadastradas'],
                ['id' => 'aImpressora', 'label' => 'Impressoras • Adicionar', 'description' => 'Cadastrar novas impressoras'],
                ['id' => 'eImpressora', 'label' => 'Impressoras • Editar', 'description' => 'Editar impressoras cadastradas'],
                ['id' => 'dImpressora', 'label' => 'Impressoras • Excluir', 'description' => 'Remover impressoras'],
                ['id' => 'vDispositivo', 'label' => 'Dispositivos • Visualizar', 'description' => 'Visualizar dispositivos autorizados'],
                ['id' => 'aDispositivo', 'label' => 'Dispositivos • Adicionar', 'description' => 'Autorizar novos dispositivos'],
                ['id' => 'eDispositivo', 'label' => 'Dispositivos • Editar', 'description' => 'Editar autorizações de dispositivos'],
                ['id' => 'dDispositivo', 'label' => 'Dispositivos • Excluir', 'description' => 'Remover autorizações de dispositivos'],
                ['id' => 'aCrediario', 'label' => 'Crediário • Adiciona', 'description' => 'Permite adicionar vendas no crediário'],
                ['id' => 'vCrediario', 'label' => 'Crediário • Visualiza', 'description' => 'Permite visualizar o crediário'],
                ['id' => 'eCrediario', 'label' => 'Crediário • Edita', 'description' => 'Permite editar dados do crediário'],
                ['id' => 'dCrediario', 'label' => 'Crediário • Exclui', 'description' => 'Permite excluir dados do crediário'],
                ['id' => 'rfCrediario', 'label' => 'Crediário • Recebe Fracionado', 'description' => 'Permite receber parcelas fracionadas'],
                ['id' => 'cdCrediario', 'label' => 'Crediário • Concede Desconto', 'description' => 'Permite dar descontos no crediário'],
                ['id' => 'aTransacao', 'label' => 'Transações • Adiciona', 'description' => 'Adicionar novas transações financeiras'],
                ['id' => 'vTransacao', 'label' => 'Transações • Visualiza', 'description' => 'Visualizar transações financeiras'],
                ['id' => 'eTransacao', 'label' => 'Transações • Edita', 'description' => 'Editar transações financeiras'],
                ['id' => 'dTransacao', 'label' => 'Transações • Exclui', 'description' => 'Excluir transações financeiras'],
                ['id' => 'aContasPagar', 'label' => 'Contas a Pagar • Adiciona', 'description' => 'Adicionar novas contas a pagar'],
                ['id' => 'vContasPagar', 'label' => 'Contas a Pagar • Visualiza', 'description' => 'Visualizar contas a pagar'],
                ['id' => 'eContasPagar', 'label' => 'Contas a Pagar • Edita', 'description' => 'Editar contas a pagar'],
                ['id' => 'dContasPagar', 'label' => 'Contas a Pagar • Exclui', 'description' => 'Excluir contas a pagar'],
                ['id' => 'aContasReceber', 'label' => 'Contas a Receber • Adiciona', 'description' => 'Adicionar novas contas a receber'],
                ['id' => 'vContasReceber', 'label' => 'Contas a Receber • Visualiza', 'description' => 'Visualizar contas a receber'],
                ['id' => 'eContasReceber', 'label' => 'Contas a Receber • Edita', 'description' => 'Editar contas a receber'],
                ['id' => 'dContasReceber', 'label' => 'Contas a Receber • Exclui', 'description' => 'Excluir contas a receber'],
                ['id' => 'vFluxoDia', 'label' => 'Contas a Receber • Visualiza Fluxo do Dia', 'description' => 'Ver fluxo de caixa do dia'],
                ['id' => 'vResumo', 'label' => 'Contas a Receber • Visualiza Resumo', 'description' => 'Ver resumo financeiro'],
                ['id' => 'RecebeContas', 'label' => 'Contas a Receber • Recebe Contas', 'description' => 'Baixar/receber contas'],
                ['id' => 'aCFOP', 'label' => 'CFOP • Adiciona', 'description' => 'Adicionar novo CFOP'],
                ['id' => 'vCFOP', 'label' => 'CFOP • Visualiza', 'description' => 'Visualizar lista de CFOPs'],
                ['id' => 'eCFOP', 'label' => 'CFOP • Edita', 'description' => 'Editar CFOP cadastrado'],
                ['id' => 'dCFOP', 'label' => 'CFOP • Exclui', 'description' => 'Excluir CFOP cadastrado'],
                ['id' => 'aGrupoTributario', 'label' => 'Grupo Tributario • Adiciona', 'description' => 'Adicionar novo grupo tributário'],
                ['id' => 'vGrupoTributario', 'label' => 'Grupo Tributario • Visualiza', 'description' => 'Visualizar grupos tributários'],
                ['id' => 'eGrupoTributario', 'label' => 'Grupo Tributario • Edita', 'description' => 'Editar grupo tributário'],
                ['id' => 'dGrupoTributario', 'label' => 'Grupo Tributario • Exclui', 'description' => 'Excluir grupo tributário'],
                ['id' => 'aUnidadeMedida', 'label' => 'Unidade de Medida • Adiciona', 'description' => 'Adicionar unidade de medida'],
                ['id' => 'vUnidadeMedida', 'label' => 'Unidade de Medida • Visualiza', 'description' => 'Visualizar unidades de medida'],
                ['id' => 'eUnidadeMedida', 'label' => 'Unidade de Medida • Edita', 'description' => 'Editar unidade de medida'],
                ['id' => 'dUnidadeMedida', 'label' => 'Unidade de Medida • Exclui', 'description' => 'Excluir unidade de medida'],
                ['id' => 'aNfe', 'label' => 'NF-e • Emitir', 'description' => 'Emitir Nota Fiscal Eletrônica'],
                ['id' => 'vNfe', 'label' => 'NF-e • Visualizar', 'description' => 'Visualizar NF-e'],
                ['id' => 'eNfe', 'label' => 'NF-e • Editar', 'description' => 'Editar NF-e'],
                ['id' => 'dNfe', 'label' => 'NF-e • Excluir', 'description' => 'Excluir NF-e'],
                ['id' => 'aNfce', 'label' => 'NFC-e • Emitir', 'description' => 'Emitir Cupom Fiscal'],
                ['id' => 'vNfce', 'label' => 'NFC-e • Visualizar', 'description' => 'Visualizar NFC-e'],
                ['id' => 'eNfce', 'label' => 'NFC-e • Editar', 'description' => 'Editar NFC-e'],
                ['id' => 'dNfce', 'label' => 'NFC-e • Excluir', 'description' => 'Excluir NFC-e'],
                ['id' => 'aNfse', 'label' => 'NFS-e • Emitir', 'description' => 'Emitir Nota de Serviço'],
                ['id' => 'vNfse', 'label' => 'NFS-e • Visualizar', 'description' => 'Visualizar NFS-e'],
                ['id' => 'eNfse', 'label' => 'NFS-e • Editar', 'description' => 'Editar NFS-e'],
                ['id' => 'dNfse', 'label' => 'NFS-e • Excluir', 'description' => 'Excluir NFS-e'],
                ['id' => 'cfgNfse', 'label' => 'NFS-e • Configurar NFS-e', 'description' => 'Configurar emissão de NFS-e'],
                ['id' => 'aOperacaoFiscal', 'label' => 'Operações Fiscais • Adicionar', 'description' => 'Criar nova operação fiscal'],
                ['id' => 'vOperacaoFiscal', 'label' => 'Operações Fiscais • Visualizar', 'description' => 'Ver operações fiscais'],
                ['id' => 'eOperacaoFiscal', 'label' => 'Operações Fiscais • Editar', 'description' => 'Editar operações fiscais'],
                ['id' => 'dOperacaoFiscal', 'label' => 'Operações Fiscais • Excluir', 'description' => 'Remover operações fiscais'],
                ['id' => 'acomprafiscal', 'label' => 'Compra Fiscal • Adiciona', 'description' => 'Dar entrada em compra via XML'],
                ['id' => 'vcomprafiscal', 'label' => 'Compra Fiscal • Visualiza', 'description' => 'Ver compras fiscais'],
                ['id' => 'ecomprafiscal', 'label' => 'Compra Fiscal • Edita', 'description' => 'Editar compras fiscais'],
                ['id' => 'dcomprafiscal', 'label' => 'Compra Fiscal • Exclui', 'description' => 'Excluir compras fiscais'],
                ['id' => 'acompramanual', 'label' => 'Compra Manual • Adiciona', 'description' => 'Dar entrada em compra manual'],
                ['id' => 'vcompramanual', 'label' => 'Compra Manual • Visualiza', 'description' => 'Ver compras manuais'],
                ['id' => 'ecompramanual', 'label' => 'Compra Manual • Edita', 'description' => 'Editar compras manuais'],
                ['id' => 'dcompramanual', 'label' => 'Compra Manual • Exclui', 'description' => 'Excluir compras manuais'],
                ['id' => 'acompras', 'label' => 'Compras • Adiciona', 'description' => 'Registrar novas compras'],
                ['id' => 'vcompras', 'label' => 'Compras • Visualiza', 'description' => 'Visualizar todas as compras'],
                ['id' => 'dcompras', 'label' => 'Compras • Exclui', 'description' => 'Remover registros de compras'],
                ['id' => 'vDFe', 'label' => 'Notas Contra o CNPJ • Visualiza DFe', 'description' => 'Ver documentos fiscais recebidos'],
                ['id' => 'BuscaDFe', 'label' => 'Notas Contra o CNPJ • Busca DFe', 'description' => 'Buscar notas na SEFAZ'],
                ['id' => 'ManifestaDFe', 'label' => 'Notas Contra o CNPJ • Manifesta DFe', 'description' => 'Manifestar ciência/confirmação'],
                ['id' => 'BaixaXML', 'label' => 'Notas Contra o CNPJ • Baixa XML', 'description' => 'Download do XML da nota'],
                ['id' => 'EntradaPorDFe', 'label' => 'Notas Contra o CNPJ • Entrada por DFe', 'description' => 'Importar nota para o estoque'],
                ['id' => 'acolaboradores', 'label' => 'Colaboradores • Adiciona', 'description' => 'Cadastrar novo colaborador'],
                ['id' => 'vcolaboradores', 'label' => 'Colaboradores • Visualiza', 'description' => 'Ver lista de colaboradores'],
                ['id' => 'ecolaboradores', 'label' => 'Colaboradores • Edita', 'description' => 'Editar dados de colaboradores'],
                ['id' => 'dcolaboradores', 'label' => 'Colaboradores • Exclui', 'description' => 'Remover colaboradores'],
                ['id' => 'aturnos', 'label' => 'Turnos • Adiciona', 'description' => 'Criar turnos de trabalho'],
                ['id' => 'vturnos', 'label' => 'Turnos • Visualiza', 'description' => 'Ver turnos cadastrados'],
                ['id' => 'eturnos', 'label' => 'Turnos • Edita', 'description' => 'Editar turnos'],
                ['id' => 'dturnos', 'label' => 'Turnos • Exclui', 'description' => 'Remover turnos'],
                ['id' => 'ajornadatrabalho', 'label' => 'Jornada de Trabalho • Adiciona', 'description' => 'Criar jornadas de trabalho'],
                ['id' => 'vjornadatrabalho', 'label' => 'Jornada de Trabalho • Visualiza', 'description' => 'Ver jornadas cadastradas'],
                ['id' => 'ejornadatrabalho', 'label' => 'Jornada de Trabalho • Edita', 'description' => 'Editar jornadas'],
                ['id' => 'djornadatrabalho', 'label' => 'Jornada de Trabalho • Exclui', 'description' => 'Remover jornadas'],
                ['id' => 'avincularjornada', 'label' => 'Vincular Jornada • Adiciona', 'description' => 'Vincular colaborador a jornada'],
                ['id' => 'vvincularjornada', 'label' => 'Vincular Jornada • Visualiza', 'description' => 'Ver vínculos de jornada'],
                ['id' => 'evincularjornada', 'label' => 'Vincular Jornada • Edita', 'description' => 'Editar vínculos de jornada'],
                ['id' => 'dvincularjornada', 'label' => 'Vincular Jornada • Exclui', 'description' => 'Remover vínculos de jornada'],
                ['id' => 'ajustificativas', 'label' => 'Justificativas • Adiciona', 'description' => 'Adicionar justificativas de falta/atraso'],
                ['id' => 'vjustificativas', 'label' => 'Justificativas • Visualiza', 'description' => 'Ver justificativas'],
                ['id' => 'ejustificativas', 'label' => 'Justificativas • Edita', 'description' => 'Editar justificativas'],
                ['id' => 'djustificativas', 'label' => 'Justificativas • Exclui', 'description' => 'Remover justificativas'],
                ['id' => 'aponto', 'label' => 'Ponto • Adiciona', 'description' => 'Registrar ponto manualmente'],
                ['id' => 'vponto', 'label' => 'Ponto • Visualiza', 'description' => 'Ver espelho de ponto'],
                ['id' => 'eponto', 'label' => 'Ponto • Edita', 'description' => 'Editar registros de ponto'],
                ['id' => 'dponto', 'label' => 'Ponto • Exclui', 'description' => 'Remover registros de ponto'],
                ['id' => 'baterPonto', 'label' => 'Ponto • Bater Ponto', 'description' => 'Permissão para registrar ponto'],
            ]
        ]);
    }

    private function validatePayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        // Tenta converter modulos de string JSON para array se necessário
        if ($request->has('modulos') && is_string($request->modulos)) {
            $decoded = json_decode($request->modulos, true);
            if (is_array($decoded)) {
                $request->merge(['modulos' => $decoded]);
            }
        }

        return $request->validate([
            'nome' => [$required, 'string', 'max:255'],
            'dias' => ['nullable', 'integer'],
            'limit_user' => ['nullable', 'integer'],
            'espaco_disco' => ['nullable', 'integer'],
            'espaco_disco_adicional' => ['nullable', 'integer'],
            'valor_usuario_adicional' => ['nullable', 'numeric'],
            'valor_espaco_adicional' => ['nullable', 'numeric'],
            'valor' => ['nullable', 'numeric'],
            'valor_revenda' => ['nullable', 'numeric'],
            'descricao' => ['nullable', 'string'],
            'modulos' => ['nullable', 'array'],
            'status' => ['nullable', 'integer'],
            'licenca_valida_ate' => ['nullable', 'date'],
        ]);
    }

    private function successList($rows)
    {
        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => $rows->items(),
            'message' => null,
            'errors' => null,
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    private function successItem(?Plano $plano, ?string $message = null, int $status = 200)
    {
        return response()->json([
            'status' => true,
            'code' => 'SUCCESS',
            'data' => $plano,
            'message' => $message,
            'errors' => null,
            'meta' => null,
        ], $status);
    }
}
