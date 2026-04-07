{extends file='parent:frontend/index/footer-navigation.tpl'}

{block name="frontend_index_footer_column_service_menu_after"}
    {block name="onco_withdrawal_after_service_menu"}
        {if $oncoWithdrawal.config.form && $oncoWithdrawal.config.showAfterFooterServiceMenu}
            <li class="navigation--entry onco-withdrawal-global-btn-container is--after-footer-service-menu">
                {include file="plugin/onco_withdrawal/global_button.tpl"}
            </li>
        {/if}
    {/block}
    {$smarty.block.parent}
{/block}

{block name="frontend_index_footer_column_information_menu_after"}
    {block name="onco_withdrawal_after_info_menu"}
        {if $oncoWithdrawal.config.form && $oncoWithdrawal.config.showAfterFooterInfoMenu}
            <li class="navigation--entry onco-withdrawal-global-btn-container is--after-footer-info-menu">
                {include file="plugin/onco_withdrawal/global_button.tpl"}
            </li>
        {/if}
    {/block}
    {$smarty.block.parent}
{/block}
