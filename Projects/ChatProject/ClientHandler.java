import java.io.*;
import java.net.Socket;

public class ClientHandler implements Runnable {

    // Defining socket and username 
    private Socket serverSocket;
    private String username;

    // ClientHandler constructor to initialize the server socket
    public ClientHandler(Socket serverSocket) {
        this.serverSocket = serverSocket;
    }

    // Overriding run method
    @Override
    public void run() {
        BufferedReader clientInput = null;
        PrintWriter clientOutput = null;

        try {
            // Declaring I/O streams
            clientInput = new BufferedReader(new InputStreamReader(serverSocket.getInputStream()));
            clientOutput = new PrintWriter(serverSocket.getOutputStream(), true);


            clientOutput.println("Enter username: ");
            username = clientInput.readLine();   // Reads the username from the client


            // If username is null or empty give a default username
            if(username == null || username.trim().equals("")) {
                username = "Guest" + ((int)(Math.random() * 1000));
            }

            // Adding the username and print writer to the clients map
            synchronized(ServerForChat.clients) {
                ServerForChat.clients.put(username, clientOutput);
            }

            broadcast(username + " has joined chat.");

            String msg = "";
            while((msg = clientInput.readLine()) != null) {
                if(msg.equalsIgnoreCase("Bye")) {
                    break;
                } else if(msg.equalsIgnoreCase("AllUsers")) {
                    synchronized(ServerForChat.clients) {
                        clientOutput.println("Users: " + ServerForChat.clients.keySet());
                    }
                } else {
                    broadcast(username + ": " + msg);
                }
            }

        } catch(Exception e) {
            System.out.println(username + " has disconnected from chat.");
        } finally {
            try {
                if(serverSocket != null) serverSocket.close();
            } catch(Exception e) {}

            synchronized(ServerForChat.clients) {
                ServerForChat.clients.remove(username);
            }

            broadcast(username + " has left the chat.");
        }
    }

    // Broadcast method to send messages to all clients
    private void broadcast(String message) {
        synchronized(ServerForChat.clients) {
            // Loops through the clients map and sends message
            for(PrintWriter w : ServerForChat.clients.values()) {
                w.println(message);
            }
        }
    }
}
