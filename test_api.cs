using System;
using System.Net.Http;
using System.Threading.Tasks;

class Program
{
    static async Task Main(string[] args)
    {
        var client = new HttpClient();
        var request = new HttpRequestMessage(HttpMethod.Put, "https://api.alamin.se/api/inventory/items/9");
        request.Headers.Add("Accept", "application/json");
        
        var response = await client.SendAsync(request);
        var content = await response.Content.ReadAsStringAsync();
        
        Console.WriteLine($"Status: {(int)response.StatusCode}");
        Console.WriteLine($"Content: {content}");
    }
}
