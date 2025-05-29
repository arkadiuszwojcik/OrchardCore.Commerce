using Lombiq.HelpfulLibraries.AspNetCore.Exceptions;
using System.Collections.Generic;

namespace OrchardCore.Commerce.Payment.Przelewy24.Models;

public class Przelewy24Response<T>
{
    public T? Data { get; set; }
    public int? ResponseCode { get; set; }

    // Error
    public string? Error { get; set; } 
    public int? Code { get; set; }

    public void ThrowIfHasErrors()
    {
        var errors = new List<string>();

        if (Error != null)
        {
            errors.Add($"{Code ?? 0}: {Error}");
        }
        else if (Data == null)
        {
            errors.Add($"{ResponseCode ?? 0}: Data is null");
        }

        FrontendException.ThrowIfAny(errors);
    }
}
