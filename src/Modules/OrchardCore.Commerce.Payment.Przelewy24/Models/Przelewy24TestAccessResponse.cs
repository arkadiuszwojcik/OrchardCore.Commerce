using Lombiq.HelpfulLibraries.AspNetCore.Exceptions;
using System.Collections.Generic;

namespace OrchardCore.Commerce.Payment.Przelewy24.Models;

public class Przelewy24TestAccessResponse
{
    public bool Data { get; set; }
    public string Error { get; set; }

    public void ThrowIfHasErrors()
    {
        var errors = new List<string>();

        if (string.IsNullOrEmpty(Error) == false)
        {
            errors.Add($"{Error}");
        }
        else if (Data == false)
        {
            errors.Add($"Data is false");
        }

        FrontendException.ThrowIfAny(errors);
    }
}
