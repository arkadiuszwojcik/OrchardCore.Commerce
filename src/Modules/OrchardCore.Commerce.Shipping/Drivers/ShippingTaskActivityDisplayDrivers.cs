using OrchardCore.Commerce.Shipping.Activities;
using OrchardCore.Workflows.Display;

namespace OrchardCore.Commerce.Shipping.Drivers;

public class CreateDraftShipmentTaskDisplayDriver : ActivityDisplayDriver<CreateDraftShipmentTask>
{
}

public class PurchaseShipmentTaskDisplayDriver : ActivityDisplayDriver<PurchaseShipmentTask>
{
}

public class CancelShipmentTaskDisplayDriver : ActivityDisplayDriver<CancelShipmentTask>
{
}
