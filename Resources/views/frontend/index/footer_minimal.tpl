{extends file='parent:frontend/index/footer_minimal.tpl'}

{block name="frontend_index_minimal_footer_menu"}
    {$smarty.block.parent}

    {block name="onco_withdrawal_minimal_footer"}
        {if $oncoWithdrawal.config.form && $oncoWithdrawal.config.showInFooter && !$hideCopyrightNotice}
            <div class="onco-withdrawal-global-btn-container is--after-footer-minimal-menu">
                {include file="plugin/onco_withdrawal/global_button.tpl"}
            </div>
        {/if}
    {/block}
{/block}
