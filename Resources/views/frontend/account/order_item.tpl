{extends file='parent:frontend/account/order_item.tpl'}

{block name="frontend_account_order_item_actions"}
    {$smarty.block.parent}

    {block name="onco_withdrawal_order_item_button"}
        {if $oncoWithdrawal.config.form && $oncoWithdrawal.config.showOrderButtons}
            <div class="onco-withdrawal-order-btn-container">
                {include file="plugin/onco_withdrawal/order_button.tpl" orderNumber=$offerPosition.ordernumber}
            </div>
        {/if}
    {/block}
{/block}
