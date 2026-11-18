# R1 legacy payment reversal guard

`POST /payment-plan/{id}/reverse-legacy` deliberately rejects valid requests from FINANCE actors on both existing allowlists with a localized HTTP 409. A manual journal reversal is not a legacy payment-domain reversal. The guard does not query the payment, acquire financial locks, or begin a write transaction. It does not add a reversal button or alter posted data.

The route regression covers authorization, validation, and no financial writes on retry. A Chrome-authenticated session sent two HTTP requests through the actual route on a disposable MariaDB target, both 409, with six financial tables unchanged. Human UAT remains DEFERRED; production remains NO-GO.