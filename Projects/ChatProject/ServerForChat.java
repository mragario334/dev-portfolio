import java.io.IOException;
import java.io.PrintWriter;
import java.net.ServerSocket;
import java.net.Socket;
import java.util.HashMap;
import java.util.Map;

public class ServerForChat {

    // Defining port and client map 
    private static final int PORT = 5002;
    public static Map<String, PrintWriter> clients = new HashMap<>();

    public static void main(String[] args) {
        // Create a server socket to listen for clients
        try (ServerSocket server = new ServerSocket(PORT)) {
            System.out.println("Server open: " + PORT);

            // Accept client connections in an infinite while loop
            while (true) {
                Socket listenSocket = server.accept();
                System.out.println("User has joined " + listenSocket.getInetAddress());


                // Create a new thread for each client connection
                new Thread(new ClientHandler(listenSocket)).start();
            }
        } catch (IOException e) {
            System.out.println("Error " + e.getMessage());
        }
    }
}
