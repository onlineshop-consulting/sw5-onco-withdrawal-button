{extends file='parent:frontend/account/sidebar.tpl'}

{block name="frontend_account_menu_link_logout"}
    {block name="onco_withdrawal_account_sidebar"}
        {if $oncoWithdrawal.config.form && $oncoWithdrawal.config.showInAccountMenu}
            <li class="onco-withdrawal-global-btn-container is--account-sidebar">
                {include file="plugin/onco_withdrawal/global_button.tpl"}
            </li>
        {/if}
    {/block}
    {$smarty.block.parent}
{/block}
